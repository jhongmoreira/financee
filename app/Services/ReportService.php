<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Category;
use App\Models\CreditCard;
use App\Models\Invoice;
use App\Models\Transaction;
use App\Support\Month;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Relatórios. Compras no cartão contam sempre pelo VENCIMENTO da fatura (como no Organizze).
 * Lançamentos em conta, pelo regime:
 *  - competência: pela data do lançamento;
 *  - caixa: só efetivados, pela data em que foram pagos/recebidos.
 * Ajustes de saldo, transferências e investimentos ficam de fora.
 */
class ReportService
{
    public function __construct(private MonthSummary $summary, private AccountBalanceService $balances, private InvoiceService $invoices) {}

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    public static function presetRange(string $preset, ?string $start = null, ?string $end = null): array
    {
        $today = CarbonImmutable::today();

        return match ($preset) {
            'last_month' => [$today->subMonthNoOverflow()->startOfMonth(), $today->subMonthNoOverflow()->endOfMonth()],
            'last_3' => [$today->subMonthsNoOverflow(2)->startOfMonth(), $today->endOfMonth()],
            'last_6' => [$today->subMonthsNoOverflow(5)->startOfMonth(), $today->endOfMonth()],
            'year' => [$today->startOfYear(), $today->endOfYear()],
            'last_12' => [$today->subMonthsNoOverflow(11)->startOfMonth(), $today->endOfMonth()],
            'custom' => self::customRange($start, $end),
            default => [$today->startOfMonth(), $today->endOfMonth()],
        };
    }

    /**
     * Totais por categoria principal (com subcategorias) e variação contra o período anterior.
     *
     * @return list<array{id: int|null, name: string, color: string, icon: string, total: int, pct: float, previous: int, change: float|null, children: list<array{name: string, total: int}>}>
     */
    public function byCategory(string $type, CarbonImmutable $start, CarbonImmutable $end, string $regime = 'competencia'): array
    {
        $current = $this->sumsByCategory($type, $start, $end, $regime);
        $days = (int) $start->diffInDays($end) + 1;
        $previous = $this->rootTotals($this->sumsByCategory($type, $start->subDays($days), $start->subDay(), $regime));

        $categories = Category::query()->get()->keyBy('id');
        $grand = array_sum($current) ?: 1;
        $roots = [];

        foreach ($current as $categoryId => $total) {
            $category = $categoryId ? $categories->get($categoryId) : null;
            $root = $category && $category->parent_id ? $categories->get($category->parent_id) : $category;
            $key = $root->id ?? 0;

            $roots[$key] ??= [
                'id' => $root?->id,
                'name' => $root->name ?? 'Sem categoria',
                'color' => $root->color ?? '#9ca3af',
                'icon' => $root->icon ?? 'tag',
                'total' => 0,
                'children' => [],
            ];
            $roots[$key]['total'] += $total;

            if ($category && $category->parent_id) {
                $roots[$key]['children'][] = ['name' => $category->name, 'total' => $total];
            } elseif ($category && $categories->where('parent_id', $category->id)->isNotEmpty()) {
                $roots[$key]['children'][] = ['name' => $category->name.' (geral)', 'total' => $total];
            }
        }

        $result = [];
        foreach ($roots as $key => $row) {
            $prev = $previous[$key] ?? 0;
            usort($row['children'], fn ($a, $b) => $b['total'] <=> $a['total']);
            $result[] = $row + [
                'pct' => round($row['total'] / $grand * 100, 1),
                'previous' => $prev,
                'change' => $prev > 0 ? round(($row['total'] - $prev) / $prev * 100, 1) : null,
            ];
        }

        usort($result, fn ($a, $b) => $b['total'] <=> $a['total']);

        return $result;
    }

    /** @return list<array{month: CarbonImmutable, label: string, income: int, expense: int, balance: int}> */
    public function monthly(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $rows = [];
        for ($m = $start->startOfMonth(); $m->lessThanOrEqualTo($end); $m = $m->addMonthNoOverflow()) {
            $t = $this->summary->totals($m);
            $rows[] = ['month' => $m, 'label' => Month::label($m), 'income' => $t['income'], 'expense' => $t['expense'], 'balance' => $t['balance']];
        }

        return $rows;
    }

    /**
     * Saldo geral (efetivado) no fim de cada mês.

     *

     * @return list<array{month: CarbonImmutable, label: string, balance: int}>
     */
    public function balanceHistory(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $ids = Account::query()->where('include_in_total', true)->pluck('id');
        $rows = [];

        for ($m = $start->startOfMonth(); $m->lessThanOrEqualTo($end); $m = $m->addMonthNoOverflow()) {
            $date = $m->endOfMonth()->min(CarbonImmutable::today());
            $balances = $this->balances->at($date);
            $rows[] = ['month' => $m, 'label' => Month::label($m), 'balance' => (int) $ids->sum(fn ($id) => $balances[$id] ?? 0)];
        }

        return $rows;
    }

    /**
     * Total das faturas por cartão e mês de vencimento.
     *
     * @return array{months: list<string>, cards: list<array{name: string, color: string, totals: list<int>}>}
     */
    public function invoicesByCard(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $months = [];
        for ($m = $start->startOfMonth(); $m->lessThanOrEqualTo($end); $m = $m->addMonthNoOverflow()) {
            $months[] = Month::key($m);
        }

        $invoices = Invoice::query()->whereIn('reference_month', $months)->get();
        $summaries = $this->invoices->summaries($invoices);

        $cards = CreditCard::query()->ordered()->get()->map(fn (CreditCard $card) => [
            'name' => $card->name,
            'color' => $card->color,
            'totals' => array_map(fn (string $key) => (int) $invoices->where('credit_card_id', $card->id)->where('reference_month', $key)
                ->sum(fn (Invoice $i) => $summaries[$i->id]['total']), $months),
        ])->filter(fn (array $c) => array_sum($c['totals']) !== 0)->values()->all();

        return ['months' => $months, 'cards' => $cards];
    }

    /**
     * Lançamentos do período (para exportação).
     *
     * @return Builder<Transaction>
     */
    public function transactionsQuery(CarbonImmutable $start, CarbonImmutable $end, string $regime = 'competencia'): Builder
    {
        return $this->inPeriod(Transaction::query()->with(['category.parent', 'account', 'creditCard', 'invoice']), $start, $end, $regime)
            ->orderBy('date')->orderBy('id');
    }

    /**
     * Aplica o período conforme o regime. Cartão sempre pelo vencimento da fatura;
     * em conta, pela data do lançamento (competência) ou do pagamento (caixa).
     *
     * @param  Builder<Transaction>  $query
     * @return Builder<Transaction>
     */
    private function inPeriod(Builder $query, CarbonImmutable $start, CarbonImmutable $end, string $regime): Builder
    {
        if ($regime !== 'caixa') {
            return $query->effectiveBetween($start, $end);
        }

        return $query->where(fn (Builder $q) => $q
            ->where(fn (Builder $q) => $q->whereNull('credit_card_id')->whereNotNull('paid_at')
                ->whereBetween('paid_at', [$start->toDateString(), $end->toDateString()]))
            ->orWhere(fn (Builder $q) => $q->whereNotNull('credit_card_id')->whereIn('invoice_id',
                Invoice::query()->select('id')->whereBetween('due_date', [$start->toDateString(), $end->toDateString()])))
        );
    }

    /**
     * Totais por categoria. Em despesas, estornos no cartão abatem; em receitas, só lançamentos em conta.
     *
     * @return array<int, int> category_id (0 = sem) => total
     */
    private function sumsByCategory(string $type, CarbonImmutable $start, CarbonImmutable $end, string $regime): array
    {
        $query = Transaction::query()->withoutAdjustments();

        if ($type === 'expense') {
            $query->where(fn ($q) => $q->where('type', 'expense')->orWhereNotNull('credit_card_id'))
                ->selectRaw("COALESCE(category_id, 0) AS cid, SUM(CASE WHEN type = 'expense' THEN amount ELSE -amount END) AS total");
        } else {
            $query->where('type', 'income')->whereNull('credit_card_id')
                ->selectRaw('COALESCE(category_id, 0) AS cid, SUM(amount) AS total');
        }

        return $this->inPeriod($query, $start, $end, $regime)->groupBy('cid')
            ->pluck('total', 'cid')->map(fn ($v) => (int) $v)->filter()->all();
    }

    /**
     * @param  array<int, int>  $sums
     * @return array<int, int>
     */
    private function rootTotals(array $sums): array
    {
        $parents = Category::query()->whereNotNull('parent_id')->pluck('parent_id', 'id');
        $result = [];
        foreach ($sums as $id => $total) {
            $root = $parents[$id] ?? $id;
            $result[$root] = ($result[$root] ?? 0) + $total;
        }

        return $result;
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    private static function customRange(?string $start, ?string $end): array
    {
        $s = $start && preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) ? CarbonImmutable::parse($start) : CarbonImmutable::today()->startOfMonth();
        $e = $end && preg_match('/^\d{4}-\d{2}-\d{2}$/', $end) ? CarbonImmutable::parse($end) : CarbonImmutable::today();

        return $s->greaterThan($e) ? [$e, $s] : [$s, $e];
    }
}
