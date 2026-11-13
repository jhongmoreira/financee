<?php

namespace App\Livewire;

use App\Models\Category;
use App\Services\BudgetService;
use App\Services\ForecastService;
use App\Support\Month;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Planejamento')]
class Planning extends Component
{
    #[Url(as: 'aba', except: 'orcamentos')]
    public string $tab = 'orcamentos'; // orcamentos | previsao

    #[Url(as: 'mes', except: '')]
    public string $month = '';

    // Formulário de orçamento
    public ?string $budget_category_id = null;

    public int $budget_amount = 0;

    public string $budget_scope = 'all'; // all (todos os meses) | month (só este mês)

    public function mount(): void
    {
        $this->month = Month::key(Month::parse($this->month));
        if (! in_array($this->tab, ['orcamentos', 'previsao'], true)) {
            $this->tab = 'orcamentos';
        }
    }

    #[On('entry-saved')]
    public function refresh(): void {}

    public function previousMonth(): void
    {
        $this->month = Month::key(Month::parse($this->month)->subMonth());
    }

    public function nextMonth(): void
    {
        $this->month = Month::key(Month::parse($this->month)->addMonth());
    }

    public function editBudget(?int $categoryId = null): void
    {
        $budgets = app(BudgetService::class);
        $this->resetValidation();
        $this->budget_category_id = $categoryId ? (string) $categoryId : null;
        $row = $categoryId ? $budgets->forMonth(Month::parse($this->month))->firstWhere('category.id', $categoryId) : null;
        $this->budget_amount = $row['budget'] ?? 0;
        $this->budget_scope = $row && ! $row['is_default'] ? 'month' : 'all';

        if (! $row && $categoryId) {
            $this->budget_amount = $budgets->suggestions(Month::parse($this->month))[$categoryId] ?? 0;
        }

        $this->dispatch('open-sheet', 'budget-form');
    }

    public function updatedBudgetCategoryId(?string $value): void
    {
        if ($value && $this->budget_amount === 0) {
            $this->budget_amount = app(BudgetService::class)->suggestions(Month::parse($this->month))[(int) $value] ?? 0;
        }
    }

    public function saveBudget(BudgetService $budgets): void
    {
        $this->validate([
            'budget_category_id' => ['required', 'integer', Rule::exists('categories', 'id')->where('user_id', Auth::id())->where('type', 'expense')->whereNull('parent_id')],
            'budget_amount' => ['required', 'integer', 'min:1', 'max:100000000000'],
            'budget_scope' => ['required', Rule::in(['all', 'month'])],
        ], attributes: ['budget_category_id' => 'categoria', 'budget_amount' => 'valor']);

        $category = Category::findOrFail($this->budget_category_id);
        $budgets->set($category, $this->budget_amount, $this->budget_scope === 'month' ? $this->month : null);

        $this->dispatch('close-sheet', 'budget-form');
        $this->dispatch('toast', message: 'Orçamento salvo.');
    }

    public function removeBudget(BudgetService $budgets): void
    {
        $category = Category::findOrFail($this->budget_category_id);
        $budgets->remove($category, $this->month);
        $budgets->remove($category, null);

        $this->dispatch('close-sheet', 'budget-form');
        $this->dispatch('toast', message: 'Orçamento removido.');
    }

    public function copyPrevious(BudgetService $budgets): void
    {
        $count = $budgets->copyFromPrevious(Month::parse($this->month));
        $this->dispatch('toast', message: $count ? "{$count} orçamento(s) copiados do mês anterior." : 'O mês anterior não tem orçamentos.', type: $count ? 'success' : 'info');
    }

    public function render(BudgetService $budgets, ForecastService $forecast): View
    {
        $month = Month::parse($this->month);
        $data = ['monthLabel' => Month::label($month)];

        if ($this->tab === 'orcamentos') {
            $rows = $budgets->forMonth($month);
            $data += [
                'rows' => $rows,
                'totals' => $budgets->totals($rows, $month),
                'categoryOptions' => Category::query()->ofType('expense')->roots()->orderBy('sort_order')->orderBy('name')->pluck('name', 'id')->all(),
                'budgetedIds' => $rows->pluck('category.id')->all(),
            ];
        } else {
            $months = $forecast->months(12);
            $data += [
                'months' => $months,
                'chart' => [
                    'type' => 'bar',
                    'legend' => false,
                    'data' => [
                        'labels' => array_map(fn ($m) => ucfirst($m['month']->locale('pt_BR')->translatedFormat('M')), $months),
                        'datasets' => [[
                            'label' => 'Saldo no fim do mês',
                            'data' => array_column($months, 'end'),
                            'backgroundColor' => array_map(fn ($m) => $m['end'] < 0 ? 'var(--expense)' : 'var(--brand)', $months),
                            'borderRadius' => 6,
                        ]],
                    ],
                ],
                'firstNegative' => collect($months)->first(fn (array $m) => $m['end'] < 0),
            ];
        }

        return view('livewire.planning', $data);
    }
}
