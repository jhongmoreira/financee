<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Invoice;
use App\Models\Transaction;
use Carbon\CarbonImmutable;

/**
 * Totais do mês (receitas e despesas, efetivadas e previstas) e saldo previsto
 * no fim do mês. Ajustes de saldo e transferências não entram como receita/despesa.
 * Compras no cartão contam como despesa no mês do VENCIMENTO da fatura (estornos abatem),
 * como no Organizze: é quando o dinheiro sai.
 */
class MonthSummary
{
    public function __construct(private AccountBalanceService $balances, private InvoiceService $invoices) {}

    /** @return array{income: int, expense: int, income_paid: int, expense_paid: int, balance: int} */
    public function totals(CarbonImmutable $month, ?int $accountId = null): array
    {
        $row = Transaction::query()->inMonth($month)->withoutAdjustments()
            ->when($accountId, fn ($q) => $q->where('account_id', $accountId))
            ->selectRaw("
                COALESCE(SUM(CASE WHEN credit_card_id IS NULL AND type = 'income' THEN amount END), 0) AS income,
                COALESCE(SUM(CASE WHEN credit_card_id IS NULL AND type = 'expense' THEN amount
                                  WHEN credit_card_id IS NOT NULL AND type = 'expense' THEN amount
                                  WHEN credit_card_id IS NOT NULL AND type = 'income' THEN -amount END), 0) AS expense,
                COALESCE(SUM(CASE WHEN credit_card_id IS NULL AND type = 'income' AND paid_at IS NOT NULL THEN amount END), 0) AS income_paid,
                COALESCE(SUM(CASE WHEN credit_card_id IS NULL AND type = 'expense' AND paid_at IS NOT NULL THEN amount
                                  WHEN credit_card_id IS NOT NULL AND type = 'expense' THEN amount
                                  WHEN credit_card_id IS NOT NULL AND type = 'income' THEN -amount END), 0) AS expense_paid
            ")->first();

        $income = (int) $row?->getAttribute('income');
        $expense = (int) $row?->getAttribute('expense');

        return [
            'income' => $income,
            'expense' => $expense,
            'income_paid' => (int) $row?->getAttribute('income_paid'),
            'expense_paid' => (int) $row?->getAttribute('expense_paid'),
            'balance' => $income - $expense,
        ];
    }

    /** Saldo geral atual (contas ativas marcadas para o total). */
    public function currentTotal(): int
    {
        return $this->sumIncluded($this->balances->current());
    }

    /**
     * Saldo geral previsto para o fim do mês: inclui pendentes até lá e desconta
     * o que falta pagar das faturas de cartão que vencem até o fim do mês.
     */
    public function projectedTotal(CarbonImmutable $month): int
    {
        return $this->sumIncluded($this->balances->projected($month->endOfMonth())) - $this->openInvoicesDueBy($month->endOfMonth());
    }

    /** Quanto falta pagar das faturas (de cartões ativos) que vencem até a data. */
    public function openInvoicesDueBy(CarbonImmutable $date): int
    {
        $invoices = Invoice::query()->where('due_date', '<=', $date->toDateString())
            ->whereHas('creditCard', fn ($q) => $q->whereNull('archived_at'))->get();

        return (int) collect($this->invoices->summaries($invoices))->sum(fn (array $s) => max(0, $s['remaining']));
    }

    /** @param array<int, int> $balances */
    private function sumIncluded(array $balances): int
    {
        $ids = Account::query()->active()->where('include_in_total', true)->pluck('id');

        return (int) $ids->sum(fn (int $id) => $balances[$id] ?? 0);
    }
}
