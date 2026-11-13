<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use App\Support\Month;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Orçamentos por categoria (de despesa).
 * Gasto = despesas do mês (efetivadas + previstas, em conta e no cartão pela data da compra)
 * na categoria e nas subcategorias. Ajustes de saldo não contam.
 */
class BudgetService
{
    /**
     * @return Collection<int, array{category: Category, budget: int, spent: int, paid: int, remaining: int, pct: int, is_default: bool}>
     */
    public function forMonth(CarbonImmutable $month): Collection
    {
        $key = Month::key($month);
        $budgets = Budget::query()->where(fn ($q) => $q->whereNull('month')->orWhere('month', $key))->get()
            ->sortBy(fn (Budget $b) => $b->month === null ? 0 : 1) // o do mês substitui o padrão
            ->keyBy('category_id');

        if ($budgets->isEmpty()) {
            return collect();
        }

        $categories = Category::query()->whereIn('id', $budgets->keys())->with('children')->get();
        $spent = $this->spentByRoot($month);

        return $categories->map(function (Category $category) use ($budgets, $spent, $key) {
            /** @var Budget $budget */
            $budget = $budgets[$category->id];
            $s = $spent[$category->id] ?? ['total' => 0, 'paid' => 0];

            return [
                'category' => $category,
                'budget' => $budget->amount,
                'spent' => $s['total'],
                'paid' => $s['paid'],
                'remaining' => $budget->amount - $s['total'],
                'pct' => $budget->amount > 0 ? (int) round($s['total'] / $budget->amount * 100) : 0,
                'is_default' => $budget->month !== $key,
            ];
        })->sortByDesc('pct')->values();
    }

    /**
     * @param  Collection<int, array{category: Category, budget: int, spent: int, paid: int, remaining: int, pct: int, is_default: bool}>  $rows
     * @return array{budget: int, spent: int, remaining: int, pct: int, per_day: int}
     */
    public function totals(Collection $rows, CarbonImmutable $month): array
    {
        $budget = (int) $rows->sum('budget');
        $spent = (int) $rows->sum('spent');
        $today = CarbonImmutable::today();
        $daysLeft = $month->isSameMonth($today) ? $today->diffInDays($month->endOfMonth()) + 1 : ($month->greaterThan($today) ? $month->daysInMonth : 0);

        return [
            'budget' => $budget,
            'spent' => $spent,
            'remaining' => $budget - $spent,
            'pct' => $budget > 0 ? (int) round($spent / $budget * 100) : 0,
            'per_day' => $daysLeft > 0 ? (int) max(0, intdiv($budget - $spent, (int) $daysLeft)) : 0,
        ];
    }

    /** Define o orçamento: só deste mês ou padrão (todos os meses). */
    public function set(Category $category, int $amount, ?string $month): void
    {
        Budget::query()->updateOrCreate(['category_id' => $category->id, 'month' => $month], ['amount' => $amount]);
    }

    public function remove(Category $category, ?string $month): void
    {
        Budget::query()->where('category_id', $category->id)->where('month', $month)->delete();
    }

    /** Copia os valores efetivos do mês anterior como orçamentos específicos deste mês. */
    public function copyFromPrevious(CarbonImmutable $month): int
    {
        $previous = $this->forMonth($month->subMonth());
        foreach ($previous as $row) {
            $this->set($row['category'], $row['budget'], Month::key($month));
        }

        return $previous->count();
    }

    /**
     * Média de gasto dos últimos 3 meses fechados, por categoria raiz de despesa.
     *
     * @return array<int, int>
     */
    public function suggestions(CarbonImmutable $month): array
    {
        $totals = [];
        for ($i = 1; $i <= 3; $i++) {
            foreach ($this->spentByRoot($month->subMonths($i)) as $id => $s) {
                $totals[$id] = ($totals[$id] ?? 0) + $s['total'];
            }
        }

        // arredonda para cima em múltiplos de R$ 10
        return array_map(fn (int $sum) => (int) (ceil($sum / 3 / 1000) * 1000), array_filter($totals));
    }

    /** @return array<int, array{total: int, paid: int}> gasto do mês por categoria RAIZ */
    public function spentByRoot(CarbonImmutable $month): array
    {
        $parents = Category::query()->whereNotNull('parent_id')->pluck('parent_id', 'id');

        $rows = Transaction::query()->inMonth($month)->withoutAdjustments()->where('type', 'expense')->whereNotNull('category_id')
            ->selectRaw('category_id, SUM(amount) AS total, SUM(CASE WHEN paid_at IS NOT NULL THEN amount ELSE 0 END) AS paid')
            ->groupBy('category_id')->get();

        $result = [];
        foreach ($rows as $row) {
            $root = $parents[$row->category_id] ?? $row->category_id;
            $result[$root]['total'] = ($result[$root]['total'] ?? 0) + (int) $row->getAttribute('total');
            $result[$root]['paid'] = ($result[$root]['paid'] ?? 0) + (int) $row->getAttribute('paid');
        }

        return $result;
    }
}
