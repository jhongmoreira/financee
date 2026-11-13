<?php

namespace App\Services;

use App\Models\Account;
use App\Models\CreditCard;
use App\Models\InstallmentPlan;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Transaction;
use App\Support\Month;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Regras de cartão de crédito.
 *
 * - Uma fatura é identificada pelo mês do VENCIMENTO (reference_month).
 * - Compra feita ANTES do dia de fechamento entra na fatura que fecha naquele mês;
 *   no dia do fechamento ou depois, entra na fatura seguinte.
 * - Dias 29–31 em meses mais curtos usam o último dia do mês.
 * - Vencimento: se o dia de vencimento é maior que o de fechamento, vence no mesmo mês
 *   do fechamento; senão, no mês seguinte.
 * - Pagamento parcial pode levar o restante para a próxima fatura; pagamento a maior
 *   sempre vira crédito na próxima (carried_out / carried_in).
 */
class InvoiceService
{
    public const STATUS_LABELS = [
        'future' => 'Futura',
        'open' => 'Aberta',
        'closed' => 'Fechada',
        'partial' => 'Paga parcialmente',
        'paid' => 'Paga',
        'overdue' => 'Vencida',
    ];

    /** Fatura em que cai uma compra feita na data informada. */
    public function invoiceFor(CreditCard $card, CarbonImmutable $purchaseDate): Invoice
    {
        $month = $purchaseDate->startOfMonth();

        if ($purchaseDate->startOfDay()->greaterThanOrEqualTo($this->closingDate($card, $month))) {
            $month = $month->addMonthNoOverflow();
        }

        return $this->byClosingMonth($card, $month);
    }

    /** Fatura aberta hoje (a que recebe compras feitas hoje). */
    public function currentInvoice(CreditCard $card): Invoice
    {
        return $this->invoiceFor($card, CarbonImmutable::today());
    }

    /** Fatura pelo mês de vencimento ("YYYY-MM"). */
    public function byReference(CreditCard $card, string|CarbonImmutable $reference): Invoice
    {
        $dueMonth = $reference instanceof CarbonImmutable ? $reference->startOfMonth() : Month::parse($reference);
        $closingMonth = $card->due_day > $card->closing_day ? $dueMonth : $dueMonth->subMonthNoOverflow();

        return $this->byClosingMonth($card, $closingMonth);
    }

    public function next(Invoice $invoice): Invoice
    {
        /** @var CreditCard $card */
        $card = $invoice->creditCard()->firstOrFail();

        return $this->byReference($card, $invoice->month()->addMonthNoOverflow());
    }

    /**
     * Totais e situação de uma ou mais faturas, com 2 consultas agregadas.
     *
     * @param  Collection<int, Invoice>  $invoices
     * @return array<int, array{purchases: int, refunds: int, carried_in: int, carried_out: int, total: int, paid: int, remaining: int, status: string, label: string}>
     */
    public function summaries(Collection $invoices): array
    {
        if ($invoices->isEmpty()) {
            return [];
        }

        $ids = $invoices->pluck('id');

        $movements = Transaction::query()->whereIn('invoice_id', $ids)
            ->selectRaw("invoice_id,
                COALESCE(SUM(CASE WHEN type = 'expense' THEN amount END), 0) AS purchases,
                COALESCE(SUM(CASE WHEN type = 'income' THEN amount END), 0) AS refunds")
            ->groupBy('invoice_id')->get()->keyBy('invoice_id');

        $payments = InvoicePayment::query()->whereIn('invoice_id', $ids)
            ->selectRaw('invoice_id, SUM(amount) AS paid')->groupBy('invoice_id')->pluck('paid', 'invoice_id');

        $today = CarbonImmutable::today();
        $result = [];

        foreach ($invoices as $invoice) {
            $row = $movements->get($invoice->id);
            $purchases = (int) $row?->getAttribute('purchases');
            $refunds = (int) $row?->getAttribute('refunds');
            $paid = (int) ($payments[$invoice->id] ?? 0);
            $total = $purchases - $refunds + $invoice->carried_in;
            $remaining = $total - $paid - $invoice->carried_out;

            $status = match (true) {
                $today->lessThan($invoice->closing_date) && $invoice->closing_date->subMonthNoOverflow()->greaterThan($today) => 'future',
                $today->lessThan($invoice->closing_date) => 'open',
                $remaining <= 0 => 'paid',
                $today->greaterThan($invoice->due_date) => 'overdue',
                $paid > 0 => 'partial',
                default => 'closed',
            };

            $result[$invoice->id] = [
                'purchases' => $purchases,
                'refunds' => $refunds,
                'carried_in' => $invoice->carried_in,
                'carried_out' => $invoice->carried_out,
                'total' => $total,
                'paid' => $paid,
                'remaining' => $remaining,
                'status' => $status,
                'label' => self::STATUS_LABELS[$status],
            ];
        }

        return $result;
    }

    /** @return array{purchases: int, refunds: int, carried_in: int, carried_out: int, total: int, paid: int, remaining: int, status: string, label: string} */
    public function summary(Invoice $invoice): array
    {
        return $this->summaries(collect([$invoice]))[$invoice->id];
    }

    /**
     * Registra um pagamento. Se sobrar saldo e $carryRemainder, o restante vai para a
     * próxima fatura; se pagar a mais, o excedente vira crédito na próxima.
     */
    public function pay(Invoice $invoice, Account $account, int $amount, CarbonImmutable $date, bool $carryRemainder = false): InvoicePayment
    {
        return DB::transaction(function () use ($invoice, $account, $amount, $date, $carryRemainder) {
            $after = $this->summary($invoice)['remaining'] - $amount;
            $carry = ($after < 0 || ($after > 0 && $carryRemainder)) ? $after : 0;

            $payment = new InvoicePayment([
                'invoice_id' => $invoice->id,
                'account_id' => $account->id,
                'amount' => $amount,
                'paid_at' => $date,
                'carried_amount' => $carry,
            ]);
            $payment->user_id = $invoice->user_id;
            $payment->save();

            if ($carry !== 0) {
                $this->moveBalance($invoice, $carry);
            }

            return $payment;
        });
    }

    public function undoPayment(InvoicePayment $payment): void
    {
        DB::transaction(function () use ($payment) {
            /** @var Invoice $invoice */
            $invoice = $payment->invoice()->firstOrFail();

            if ($payment->carried_amount !== 0) {
                $this->moveBalance($invoice, -$payment->carried_amount);
            }

            $payment->delete();
        });
    }

    /**
     * Lança uma compra (ou estorno) no cartão, à vista ou parcelada.
     * As parcelas vão para faturas consecutivas a partir da primeira.
     *
     * @return Collection<int, Transaction>
     */
    public function createPurchase(
        CreditCard $card,
        string $description,
        ?int $categoryId,
        CarbonImmutable $date,
        int $amount,
        int $installments = 1,
        bool $isRefund = false,
        ?string $firstReference = null,
        ?string $notes = null,
    ): Collection {
        return DB::transaction(function () use ($card, $description, $categoryId, $date, $amount, $installments, $isRefund, $firstReference, $notes) {
            $first = $firstReference ? $this->byReference($card, $firstReference) : $this->invoiceFor($card, $date);
            $installments = $isRefund ? 1 : max(1, $installments);

            $plan = null;
            $parts = [$amount];

            if ($installments > 1) {
                $plan = new InstallmentPlan([
                    'description' => $description,
                    'total_amount' => $amount,
                    'installments' => $installments,
                    'first_date' => $date,
                    'credit_card_id' => $card->id,
                    'category_id' => $categoryId,
                ]);
                $plan->user_id = $card->user_id;
                $plan->save();
                $parts = InstallmentPlan::split($amount, $installments);
            }

            $created = collect();

            foreach ($parts as $i => $part) {
                $invoice = $i === 0 ? $first : $this->byReference($card, $first->month()->addMonthsNoOverflow($i));
                $partDate = $date->addMonthsNoOverflow($i);

                $transaction = new Transaction([
                    'type' => $isRefund ? 'income' : 'expense',
                    'description' => $description,
                    'notes' => $notes,
                    'amount' => $part,
                    'date' => $partDate,
                    'paid_at' => $partDate, // no cartão a compra já está efetivada; quem "paga" é a fatura
                    'category_id' => $categoryId,
                    'credit_card_id' => $card->id,
                    'invoice_id' => $invoice->id,
                    'installment_plan_id' => $plan?->id,
                    'installment_number' => $plan ? $i + 1 : null,
                    'installment_total' => $plan ? $installments : null,
                ]);
                $transaction->user_id = $card->user_id;
                $transaction->save();
                $created->push($transaction);
            }

            return $created;
        });
    }

    /** Antecipa as parcelas futuras de um parcelamento para a fatura atual do cartão. */
    public function anticipate(InstallmentPlan $plan): int
    {
        /** @var CreditCard $card */
        $card = CreditCard::query()->findOrFail($plan->credit_card_id);
        $current = $this->currentInvoice($card);

        $future = $plan->transactions()->whereHas('invoice', fn ($q) => $q->where('reference_month', '>', $current->reference_month))->get();

        foreach ($future as $transaction) {
            $transaction->update(['invoice_id' => $current->id, 'date' => CarbonImmutable::today(), 'paid_at' => CarbonImmutable::today()]);
        }

        return $future->count();
    }

    /** Leva $amount (positivo = dívida, negativo = crédito) desta fatura para a próxima. */
    private function moveBalance(Invoice $invoice, int $amount): void
    {
        $next = $this->next($invoice);
        $invoice->increment('carried_out', $amount);
        $next->increment('carried_in', $amount);
    }

    private function byClosingMonth(CreditCard $card, CarbonImmutable $closingMonth): Invoice
    {
        $closing = $this->closingDate($card, $closingMonth);
        $dueMonth = $card->due_day > $card->closing_day ? $closingMonth->startOfMonth() : $closingMonth->startOfMonth()->addMonthNoOverflow();
        $due = $dueMonth->setDay(min($card->due_day, $dueMonth->daysInMonth));

        $invoice = Invoice::query()->withoutGlobalScopes()->firstOrNew([
            'credit_card_id' => $card->id,
            'reference_month' => Month::key($dueMonth),
        ]);

        if (! $invoice->exists) {
            $invoice->user_id = $card->user_id;
            $invoice->closing_date = $closing;
            $invoice->due_date = $due;
            $invoice->save();
        }

        return $invoice;
    }

    private function closingDate(CreditCard $card, CarbonImmutable $month): CarbonImmutable
    {
        $month = $month->startOfMonth();

        return $month->setDay(min($card->closing_day, $month->daysInMonth));
    }
}
