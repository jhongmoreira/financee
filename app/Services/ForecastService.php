<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Invoice;
use App\Models\Transaction;
use App\Support\Month;
use Carbon\CarbonImmutable;

/**
 * Previsão do saldo geral mês a mês.
 *
 *   saldo final do mês = saldo inicial + receitas previstas − despesas previstas − faturas a pagar
 *
 * - Parte do saldo atual (só efetivados) das contas incluídas no total.
 * - Mês atual: considera também pendências vencidas (ainda não pagas).
 * - Faturas: o que falta pagar, no mês do vencimento.
 * - Transferências entre contas não mudam o saldo geral (ignoradas).
 */
class ForecastService
{
    public function __construct(private MonthSummary $summary, private InvoiceService $invoices) {}

    /** @return list<array{month: CarbonImmutable, label: string, start: int, income: int, expense: int, invoices: int, end: int}> */
    public function months(int $count = 12): array
    {
        $today = CarbonImmutable::today();
        $first = $today->startOfMonth();
        $last = $first->addMonthsNoOverflow($count - 1)->endOfMonth();

        $accountIds = Account::query()->active()->where('include_in_total', true)->pluck('id');

        // Pendentes em conta até o fim do período, agrupados por mês (vencidos contam no mês atual)
        $pending = Transaction::query()->whereNull('paid_at')->whereIn('account_id', $accountIds)
            ->where('date', '<=', $last->toDateString())
            ->get(['type', 'amount', 'date'])
            ->groupBy(fn (Transaction $t) => $t->date->lessThan($first) ? Month::key($first) : Month::key($t->date));

        // Faturas com saldo a pagar até o fim do período (vencidas contam no mês atual)
        $invoices = Invoice::query()->where('due_date', '<=', $last->toDateString())
            ->whereHas('creditCard', fn ($q) => $q->whereNull('archived_at'))->get();
        $summaries = $this->invoices->summaries($invoices);
        $invoiceByMonth = $invoices->groupBy(fn (Invoice $i) => $i->due_date->lessThan($first) ? Month::key($first) : Month::key($i->due_date))
            ->map(fn ($group) => (int) $group->sum(fn (Invoice $i) => max(0, $summaries[$i->id]['remaining'])));

        $balance = $this->summary->currentTotal();
        $result = [];

        for ($i = 0; $i < $count; $i++) {
            $month = $first->addMonthsNoOverflow($i);
            $key = Month::key($month);
            $items = $pending->get($key, collect());

            $income = (int) $items->where('type', 'income')->sum('amount');
            $expense = (int) $items->where('type', 'expense')->sum('amount');
            $cards = (int) ($invoiceByMonth[$key] ?? 0);
            $end = $balance + $income - $expense - $cards;

            $result[] = [
                'month' => $month,
                'label' => Month::label($month),
                'start' => $balance,
                'income' => $income,
                'expense' => $expense,
                'invoices' => $cards,
                'end' => $end,
            ];

            $balance = $end;
        }

        return $result;
    }
}
