<?php

namespace App\Services;

use App\Models\CreditCard;
use App\Models\InstallmentPlan;
use App\Models\Recurrence;
use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Lançamentos fixos e parcelamentos em conta.
 *
 * Recorrências são "materializadas": geramos os lançamentos previstos com até
 * antecedência: o restante do ano atual + o ano seguinte inteiro. Assim extrato, previsão, orçamentos e relatórios
 * tratam fixos e avulsos da mesma forma, com consultas simples.
 */
class RecurrenceService
{
    /** Gera até 31/12 do ano seguinte (ex.: lançado em nov/2026 → até dez/2027). */
    public static function horizon(): CarbonImmutable
    {
        return CarbonImmutable::today()->addYear()->endOfYear()->startOfDay();
    }

    public function __construct(private InvoiceService $invoices) {}

    /** Gera os lançamentos de uma recorrência até o horizonte (ou a data final). */
    public function generate(Recurrence $recurrence, ?CarbonImmutable $until = null): int
    {
        $until ??= self::horizon();

        // No cartão também: as compras fixas futuras aparecem nas faturas e na previsão,
        // mas só ocupam limite a partir da data da cobrança (ver CreditCard::usedLimit).

        if ($recurrence->end_date && $recurrence->end_date->lessThan($until)) {
            $until = $recurrence->end_date;
        }

        $created = 0;

        DB::transaction(function () use ($recurrence, $until, &$created) {
            for ($n = 0; $n < 1000; $n++) {
                $date = $recurrence->occurrence($n);

                if ($date->greaterThan($until)) {
                    break;
                }
                if ($recurrence->generated_until && $date->lessThanOrEqualTo($recurrence->generated_until)) {
                    continue;
                }

                $this->createOccurrence($recurrence, $date);
                $created++;
            }

            $recurrence->generated_until = $until;
            $recurrence->save();
        });

        return $created;
    }

    /** Rotina diária: estende todas as recorrências e efetiva os débitos automáticos. */
    public function runDaily(): int
    {
        $count = 0;

        Recurrence::query()->withoutGlobalScopes()
            ->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', today()->toDateString()))
            ->each(function (Recurrence $recurrence) use (&$count) {
                $count += $this->generate($recurrence);
            });

        // Débito automático: efetiva no dia
        Transaction::query()->withoutGlobalScopes()->whereNull('paid_at')
            ->where('date', '<=', today()->toDateString())
            ->whereHas('recurrence', fn ($q) => $q->withoutGlobalScopes()->where('auto_mark_paid', true))
            ->each(fn (Transaction $t) => $t->update(['paid_at' => $t->date]));

        return $count;
    }

    /**
     * Altera a recorrência a partir de uma data: lançamentos pendentes dali em diante
     * são apagados e gerados de novo com os dados novos. Os já pagos ficam como estão.
     *
     * @param  array<string, mixed>  $changes
     */
    public function updateFrom(Recurrence $recurrence, CarbonImmutable $from, array $changes): void
    {
        DB::transaction(function () use ($recurrence, $from, $changes) {
            $this->deletePendingFrom($recurrence, $from);

            $recurrence->fill($changes);
            // Gera de novo a partir de $from, mantendo as ocorrências anteriores.
            $recurrence->generated_until = $from->subDay();
            $recurrence->save();

            $this->generate($recurrence);
        });
    }

    /** Encerra a recorrência: nada a partir de $from (pendentes apagados; pagos ficam). */
    public function stop(Recurrence $recurrence, CarbonImmutable $from): void
    {
        DB::transaction(function () use ($recurrence, $from) {
            $this->deletePendingFrom($recurrence, $from);
            $recurrence->update(['end_date' => $from->subDay()]);
        });
    }

    /** Parcelamento em conta (boleto/carnê/financiamento): N lançamentos previstos mensais. */
    public function createAccountInstallments(
        string $type,
        string $description,
        int $total,
        int $installments,
        CarbonImmutable $firstDate,
        int $accountId,
        ?int $categoryId,
        bool $firstPaid,
        ?string $notes = null,
    ): InstallmentPlan {
        return DB::transaction(function () use ($type, $description, $total, $installments, $firstDate, $accountId, $categoryId, $firstPaid, $notes) {
            $plan = InstallmentPlan::create([
                'description' => $description,
                'total_amount' => $total,
                'installments' => $installments,
                'first_date' => $firstDate,
                'account_id' => $accountId,
                'category_id' => $categoryId,
            ]);

            foreach (InstallmentPlan::split($total, $installments) as $i => $amount) {
                $date = $firstDate->addMonthsNoOverflow($i);
                Transaction::create([
                    'type' => $type,
                    'description' => $description,
                    'notes' => $notes,
                    'amount' => $amount,
                    'date' => $date,
                    'paid_at' => $i === 0 && $firstPaid ? min($date, CarbonImmutable::today()) : null,
                    'account_id' => $accountId,
                    'category_id' => $categoryId,
                    'installment_plan_id' => $plan->id,
                    'installment_number' => $i + 1,
                    'installment_total' => $installments,
                ]);
            }

            return $plan;
        });
    }

    private function createOccurrence(Recurrence $recurrence, CarbonImmutable $date): void
    {
        if ($recurrence->credit_card_id) {
            $card = CreditCard::query()->withoutGlobalScopes()->find($recurrence->credit_card_id);
            if ($card) {
                $this->invoices->createPurchase($card, $recurrence->description, $recurrence->category_id, $date, $recurrence->amount)
                    ->each(fn (Transaction $t) => $t->update(['recurrence_id' => $recurrence->id]));
            }

            return;
        }

        $paid = $recurrence->auto_mark_paid && $date->lessThanOrEqualTo(CarbonImmutable::today());

        $transaction = new Transaction([
            'type' => $recurrence->type,
            'description' => $recurrence->description,
            'amount' => $recurrence->amount,
            'date' => $date,
            'paid_at' => $paid ? $date : null,
            'account_id' => $recurrence->account_id,
            'category_id' => $recurrence->category_id,
            'recurrence_id' => $recurrence->id,
        ]);
        $transaction->user_id = $recurrence->user_id;
        $transaction->save();
    }

    private function deletePendingFrom(Recurrence $recurrence, CarbonImmutable $from): void
    {
        // Em conta: só os pendentes. No cartão: as compras de faturas que ainda não fecharam.
        Transaction::query()->withoutGlobalScopes()->where('recurrence_id', $recurrence->id)
            ->where('date', '>=', $from->toDateString())
            ->where(fn ($q) => $q->whereNull('paid_at')->orWhereNotNull('credit_card_id'))
            ->get()->each->delete();
    }
}
