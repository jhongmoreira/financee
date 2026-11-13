<?php

namespace App\Livewire;

use App\Models\Account;
use App\Models\Category;
use App\Models\CreditCard;
use App\Models\InvestmentPortfolio;
use App\Models\Invoice;
use App\Models\Transaction;
use App\Models\Transfer;
use App\Services\BudgetService;
use App\Services\InvestmentService;
use App\Services\InvoiceService;
use App\Services\MonthSummary;
use App\Support\Month;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Início')]
class Home extends Component
{
    #[On('entry-saved')]
    public function refresh(): void
    {
        // re-renderiza
    }

    public function togglePaid(string $kind, int $id): void
    {
        $model = $kind === 'transfer' ? Transfer::findOrFail($id) : Transaction::findOrFail($id);
        $model->update(['paid_at' => $model->paid_at ? null : min($model->date, today())]);
        $this->dispatch('toast', message: 'Marcado como efetivado.');
    }

    public function render(MonthSummary $summary, InvoiceService $invoices, InvestmentService $investments, BudgetService $budgets): View
    {
        $month = CarbonImmutable::today()->startOfMonth();

        $cards = CreditCard::query()->active()->ordered()->get();
        $currentInvoices = $cards->mapWithKeys(fn (CreditCard $c) => [$c->id => $invoices->currentInvoice($c)]);

        // Faturas fechadas com saldo a pagar que vencem em até 7 dias (ou já venceram)
        $dueInvoices = Invoice::query()->with('creditCard')
            ->whereHas('creditCard', fn ($q) => $q->whereNull('archived_at'))
            ->where('closing_date', '<=', today()->toDateString())
            ->where('due_date', '<=', today()->addDays(7)->toDateString())
            ->where('due_date', '>=', today()->subMonths(3)->toDateString())
            ->orderBy('due_date')->get();
        $dueSummaries = $invoices->summaries($dueInvoices);
        $invoiceRows = $dueInvoices->filter(fn (Invoice $i) => $dueSummaries[$i->id]['remaining'] > 0)
            ->map(fn (Invoice $i) => [
                'kind' => 'invoice',
                'id' => $i->id,
                'date' => $i->due_date,
                'description' => 'Fatura '.$i->creditCard?->name.' ('.$i->shortLabel().')',
                'subtitle' => Month::dayLabel($i->due_date),
                'amount' => -$dueSummaries[$i->id]['remaining'],
                'paid' => false,
                'overdue' => $i->due_date->isBefore(today()),
                'icon' => 'credit-card',
                'color' => $i->creditCard->color ?? '#7c3aed',
                'type' => 'invoice',
                'href' => route('cards.invoice', ['card' => $i->credit_card_id, 'mes' => $i->reference_month]),
            ]);

        // Pendências: vencidas + próximos 7 dias
        $pending = Transaction::query()->with(['category', 'account', 'creditCard'])->whereNull('paid_at')->whereNull('credit_card_id')
            ->where('date', '<=', today()->addDays(7)->toDateString())
            ->orderBy('date')->limit(6)->get();

        $recent = Transaction::query()->with(['category', 'account', 'creditCard'])->whereNotNull('paid_at')
            ->where('date', '<=', today()->toDateString()) // parcelas futuras do cartão não são "últimos"
            ->latest('date')->latest('id')->limit(5)->get();

        return view('livewire.home', [
            'monthLabel' => Month::label($month),
            'total' => $summary->currentTotal(),
            'projected' => $summary->projectedTotal($month),
            'totals' => $summary->totals($month),
            'accounts' => Account::query()->active()->ordered()->with('institution')->get(),
            'cardsCount' => $cards->count(),
            'investedTotal' => $investments->totalValue(),
            'unread' => auth()->user()?->unreadNotifications()->count() ?? 0,
            'categoryChart' => $this->categoryChart($budgets, $month),
            'historyChart' => $this->historyChart($summary, $month),
            'budgets' => $budgets->forMonth($month)->take(3),
            'hasInvestments' => InvestmentPortfolio::query()->exists(),
            'cards' => $cards,
            'currentInvoices' => $currentInvoices,
            'invoiceSummaries' => $invoices->summaries($currentInvoices->values()),
            'pending' => $invoiceRows->values()->concat($pending->map(fn (Transaction $t) => $this->row($t)))->sortBy(fn ($r) => $r['date']->toDateString())->values(),
            'recent' => $recent->map(fn (Transaction $t) => $this->row($t)),
        ]);
    }

    /** @return array<string, mixed>|null rosca de despesas do mês por categoria */
    private function categoryChart(BudgetService $budgets, CarbonImmutable $month): ?array
    {
        $spent = collect($budgets->spentByRoot($month))->map(fn (array $s) => $s['total'])->filter()->sortDesc();

        if ($spent->isEmpty()) {
            return null;
        }

        $categories = Category::query()->whereIn('id', $spent->keys())->get()->keyBy('id');
        $top = $spent->take(6);
        $others = $spent->slice(6)->sum();

        $labels = $top->keys()->map(fn ($id) => $categories[$id]->name ?? '—')->all();
        $colors = $top->keys()->map(fn ($id) => $categories[$id]->color ?? '#6b7280')->all();
        $values = $top->values()->all();

        if ($others > 0) {
            $labels[] = 'Outras';
            $colors[] = '#9ca3af';
            $values[] = $others;
        }

        return [
            'type' => 'doughnut',
            'data' => ['labels' => $labels, 'datasets' => [['data' => $values, 'backgroundColor' => $colors, 'borderWidth' => 0]]],
            'legendItems' => array_map(null, $labels, $colors, $values),
            'total' => (int) $spent->sum(),
        ];
    }

    /** @return array<string, mixed> barras de receitas × despesas dos últimos 6 meses */
    private function historyChart(MonthSummary $summary, CarbonImmutable $month): array
    {
        $labels = $income = $expense = [];

        for ($i = 5; $i >= 0; $i--) {
            $m = $month->subMonthsNoOverflow($i);
            $totals = $summary->totals($m);
            $labels[] = ucfirst($m->locale('pt_BR')->translatedFormat('M'));
            $income[] = $totals['income'];
            $expense[] = $totals['expense'];
        }

        return [
            'type' => 'bar',
            'data' => ['labels' => $labels, 'datasets' => [
                ['label' => 'Receitas', 'data' => $income, 'backgroundColor' => 'var(--income)', 'borderRadius' => 4],
                ['label' => 'Despesas', 'data' => $expense, 'backgroundColor' => 'var(--expense)', 'borderRadius' => 4],
            ]],
        ];
    }

    /** @return array<string, mixed> */
    private function row(Transaction $t): array
    {
        return [
            'kind' => 'transaction',
            'id' => $t->id,
            'date' => $t->date,
            'description' => $t->description,
            'subtitle' => collect([Month::dayLabel($t->date), $t->is_adjustment ? 'Ajuste de saldo' : $t->category?->name, $t->creditCard ? 'Cartão '.$t->creditCard->name : null])->filter()->implode(' · '),
            'amount' => $t->signedAmount(),
            'paid' => $t->isPaid(),
            'overdue' => $t->isOverdue(),
            'icon' => $t->is_adjustment ? 'scale' : ($t->category->icon ?? 'tag'),
            'color' => $t->category->color ?? '#6b7280',
            'type' => $t->type,
        ];
    }
}
