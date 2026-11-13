<?php

namespace App\Livewire\Transactions;

use App\Models\Account;
use App\Models\Category;
use App\Models\InvestmentMovement;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Transaction;
use App\Models\Transfer;
use App\Services\InvoiceService;
use App\Services\MonthSummary;
use App\Support\Money;
use App\Support\Month;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Extrato do mês: lançamentos e transferências agrupados por dia.
 *
 * @phpstan-type Row array{kind: string, id: int, date: CarbonImmutable, day: string, description: string, subtitle: string, amount: int, neutral?: bool, paid: bool, overdue: bool, icon: string, color: string, type: string, href?: string|null, hideStatus?: bool}
 */
#[Title('Lançamentos')]
class Index extends Component
{
    #[Url(as: 'mes', except: '')]
    public string $month = '';

    #[Url(as: 'tipo', except: '')]
    public string $type = ''; // '' | income | expense | transfer

    #[Url(as: 'conta', except: '')]
    public string $account = '';

    #[Url(as: 'categoria', except: '')]
    public string $category = '';

    #[Url(as: 'status', except: '')]
    public string $status = ''; // '' | paid | pending

    #[Url(as: 'busca', except: '')]
    public string $search = '';

    /** Ordem da lista: asc (do 1º dia ao último — padrão) | desc. Salva nas preferências do usuário. */
    public string $order = 'asc';

    public function mount(): void
    {
        $this->order = Auth::user()?->setting('statement_order') === 'desc' ? 'desc' : 'asc';
        $this->month = Month::key(Month::parse($this->month));
    }

    public function previousMonth(): void
    {
        $this->month = Month::key(Month::parse($this->month)->subMonth());
    }

    public function nextMonth(): void
    {
        $this->month = Month::key(Month::parse($this->month)->addMonth());
    }

    public function toggleOrder(): void
    {
        $this->order = $this->order === 'asc' ? 'desc' : 'asc';
        Auth::user()?->updateSetting('statement_order', $this->order);
    }

    public function clearFilters(): void
    {
        $this->reset('type', 'account', 'category', 'status', 'search');
    }

    public function togglePaid(string $kind, int $id): void
    {
        $model = $kind === 'transfer' ? Transfer::findOrFail($id) : Transaction::findOrFail($id);
        $model->update(['paid_at' => $model->paid_at ? null : min($model->date, today())]);

        $this->dispatch('entry-saved');
        $this->dispatch('toast', message: $model->paid_at ? 'Marcado como efetivado.' : 'Marcado como pendente.');
    }

    #[On('entry-saved')]
    public function refresh(): void
    {
        // re-renderiza
    }

    public function render(MonthSummary $summary): View
    {
        $month = Month::parse($this->month);
        $rows = $this->rows($month);

        return view('livewire.transactions.index', [
            'monthLabel' => Month::label($month),
            'groups' => $rows->groupBy(fn (array $row): string => $row['day']),
            'totals' => $summary->totals($month, $this->account !== '' ? (int) $this->account : null),
            'accounts' => Account::query()->ordered()->pluck('name', 'id')->all(),
            'categories' => Category::query()->roots()->with('children')->orderBy('type')->orderBy('sort_order')->get(),
            'activeFilters' => collect([$this->type, $this->account, $this->category, $this->status, $this->search])->filter()->count(),
        ]);
    }

    /** @return Collection<int, Row> */
    private function rows(CarbonImmutable $month): Collection
    {
        /** @var Collection<int, Row> $rows */
        $rows = collect();
        $search = trim($this->search);

        // Sem busca/categoria, o cartão aparece como uma linha por fatura (como no Organizze).
        $showCardItems = $this->category !== '' || $search !== '';

        if ($this->type !== 'transfer') {
            $categoryIds = $this->categoryIds();

            Transaction::query()->inMonth($month)->with(['category', 'account', 'creditCard', 'invoice'])
                ->when(! $showCardItems, fn ($q) => $q->whereNull('credit_card_id'))
                ->when(in_array($this->type, ['income', 'expense'], true), fn ($q) => $q->where('type', $this->type))
                ->when($this->account !== '', fn ($q) => $q->where('account_id', (int) $this->account))
                ->when($categoryIds, fn ($q) => $q->whereIn('category_id', $categoryIds))
                ->when($this->status === 'paid', fn ($q) => $q->whereNotNull('paid_at'))
                ->when($this->status === 'pending', fn ($q) => $q->whereNull('paid_at'))
                ->when($search !== '', fn ($q) => $q->where('description', 'like', '%'.$search.'%'))
                ->get()
                ->each(function (Transaction $t) use ($rows) {
                    $effective = $t->effectiveDate();
                    $rows->push([
                        'kind' => 'transaction',
                        'id' => $t->id,
                        'date' => $effective,
                        'day' => $effective->toDateString(),
                        'description' => $t->description.($t->installmentLabel() ? ' ('.$t->installmentLabel().')' : ''),
                        'subtitle' => collect([
                            $t->recurrence_id ? 'Fixo' : null,
                            $t->is_adjustment ? 'Ajuste de saldo' : ($t->isCard() && $t->type === 'income' ? 'Estorno' : $t->category?->name),
                            $t->account->name ?? ($t->creditCard ? 'Cartão '.$t->creditCard->name : null),
                            $t->isCard() ? 'compra '.$t->date->format('d/m').' · fatura '.$t->invoice?->shortLabel() : null,
                        ])->filter()->implode(' · '),
                        'amount' => $t->signedAmount(),
                        'paid' => $t->isPaid(),
                        'overdue' => $t->isOverdue(),
                        'icon' => $t->is_adjustment ? 'scale' : ($t->category->icon ?? ($t->isCard() ? 'credit-card' : 'tag')),
                        'color' => $t->category->color ?? '#6b7280',
                        'type' => $t->type,
                    ]);
                });
        }

        if (in_array($this->type, ['', 'transfer'], true) && $this->category === '') {
            Transfer::query()->whereBetween('date', [$month->startOfMonth()->toDateString(), $month->endOfMonth()->toDateString()])
                ->with(['fromAccount', 'toAccount'])
                ->when($this->account !== '', fn ($q) => $q->where(fn ($q) => $q->where('from_account_id', (int) $this->account)->orWhere('to_account_id', (int) $this->account)))
                ->when($this->status === 'paid', fn ($q) => $q->whereNotNull('paid_at'))
                ->when($this->status === 'pending', fn ($q) => $q->whereNull('paid_at'))
                ->when($search !== '', fn ($q) => $q->where('description', 'like', '%'.$search.'%'))
                ->get()
                ->each(function (Transfer $t) use ($rows) {
                    $sign = $this->account === '' ? 0 : ((int) $this->account === $t->from_account_id ? -1 : 1);
                    $rows->push([
                        'kind' => 'transfer',
                        'id' => $t->id,
                        'date' => $t->date,
                        'day' => $t->date->toDateString(),
                        'description' => $t->description ?: 'Transferência',
                        'subtitle' => $t->fromAccount?->name.' → '.$t->toAccount?->name,
                        'amount' => $sign === 0 ? $t->amount : $sign * $t->amount,
                        'neutral' => $sign === 0,
                        'paid' => $t->isPaid(),
                        'overdue' => ! $t->isPaid() && $t->date->isBefore(today()),
                        'icon' => 'arrows-right-left',
                        'color' => '#0284c7',
                        'type' => 'transfer',
                    ]);
                });
        }

        // Pagamentos de fatura (saem da conta; não são despesa por categoria)
        if ($this->account !== '' && in_array($this->type, ['', 'expense'], true) && $this->category === '' && $this->status !== 'pending') {
            InvoicePayment::query()->whereBetween('paid_at', [$month->startOfMonth()->toDateString(), $month->endOfMonth()->toDateString()])
                ->with(['invoice.creditCard', 'account'])
                ->where('account_id', (int) $this->account)
                ->get()
                ->each(function (InvoicePayment $p) use ($rows, $search) {
                    $description = 'Pagamento fatura '.($p->invoice->creditCard->name ?? '');
                    if ($search !== '' && ! str_contains(mb_strtolower($description), mb_strtolower($search))) {
                        return;
                    }
                    $rows->push([
                        'kind' => 'payment',
                        'id' => $p->id,
                        'date' => $p->paid_at,
                        'day' => $p->paid_at->toDateString(),
                        'description' => $description,
                        'subtitle' => collect([$p->invoice?->shortLabel(), $p->account?->name])->filter()->implode(' · '),
                        'amount' => -$p->amount,
                        'paid' => true,
                        'overdue' => false,
                        'icon' => 'credit-card',
                        'color' => $p->invoice->creditCard->color ?? '#7c3aed',
                        'type' => 'payment',
                        'href' => $p->invoice ? route('cards.invoice', ['card' => $p->invoice->credit_card_id, 'mes' => $p->invoice->reference_month]) : null,
                    ]);
                });
        }

        // Faturas de cartão que vencem no mês: uma linha por fatura, com o total
        if ($this->account === '' && ! $showCardItems && in_array($this->type, ['', 'expense'], true)) {
            $invoices = Invoice::query()->with('creditCard')
                ->whereBetween('due_date', [$month->startOfMonth()->toDateString(), $month->endOfMonth()->toDateString()])
                ->get();
            $summaries = app(InvoiceService::class)->summaries($invoices);
            $counts = Transaction::query()->whereIn('invoice_id', $invoices->pluck('id'))
                ->selectRaw('invoice_id, COUNT(*) AS n')->groupBy('invoice_id')->pluck('n', 'invoice_id');

            foreach ($invoices as $invoice) {
                $sum = $summaries[$invoice->id];
                $isPaid = $sum['status'] === 'paid';

                if (($sum['total'] === 0 && $sum['paid'] === 0)
                    || ($this->status === 'paid' && ! $isPaid) || ($this->status === 'pending' && $isPaid)) {
                    continue;
                }

                $n = (int) ($counts[$invoice->id] ?? 0);
                $rows->push([
                    'kind' => 'invoice',
                    'id' => $invoice->id,
                    'date' => $invoice->due_date,
                    'day' => $invoice->due_date->toDateString(),
                    'description' => 'Fatura '.($invoice->creditCard->name ?? 'Cartão'),
                    'subtitle' => $sum['label'].' · '.$n.' '.($n === 1 ? 'lançamento' : 'lançamentos')
                        .($sum['paid'] > 0 && ! $isPaid ? ' · falta '.Money::format(max(0, $sum['remaining'])) : ''),
                    'amount' => -$sum['total'],
                    'paid' => $isPaid,
                    'overdue' => $sum['status'] === 'overdue',
                    'icon' => 'credit-card',
                    'color' => $invoice->creditCard->color ?? '#7c3aed',
                    'type' => 'invoice',
                    'href' => route('cards.invoice', ['card' => $invoice->credit_card_id, 'mes' => $invoice->reference_month]),
                    'hideStatus' => true,
                ]);
            }
        }

        // Aportes/resgates/rendimentos de investimentos que movimentaram uma conta
        if (in_array($this->type, ['', 'transfer'], true) && $this->category === '' && $this->status !== 'pending') {
            InvestmentMovement::query()->whereNotNull('account_id')->where('type', '!=', 'valuation')
                ->whereBetween('date', [$month->startOfMonth()->toDateString(), $month->endOfMonth()->toDateString()])
                ->with(['asset', 'account'])
                ->when($this->account !== '', fn ($q) => $q->where('account_id', (int) $this->account))
                ->get()
                ->each(function (InvestmentMovement $m) use ($rows, $search) {
                    $description = $m->typeLabel().': '.($m->asset->name ?? '');
                    if ($search !== '' && ! str_contains(mb_strtolower($description), mb_strtolower($search))) {
                        return;
                    }
                    $rows->push([
                        'kind' => 'investment',
                        'id' => $m->id,
                        'date' => $m->date,
                        'day' => $m->date->toDateString(),
                        'description' => $description,
                        'subtitle' => 'Investimentos · '.($m->account->name ?? ''),
                        'amount' => $m->accountEffect(),
                        'paid' => true,
                        'overdue' => false,
                        'icon' => 'arrow-trending-up',
                        'color' => '#4f46e5',
                        'type' => 'investment',
                        'href' => route('investments.asset', $m->asset_id),
                    ]);
                });
        }

        // Por data; dentro do mesmo dia, na ordem em que foram lançados
        $key = fn (array $row) => $row['date']->toDateString().sprintf('%010d', $row['id']);

        return ($this->order === 'desc' ? $rows->sortByDesc($key) : $rows->sortBy($key))->values();
    }

    /** @return list<int> categoria escolhida + subcategorias */
    private function categoryIds(): array
    {
        if ($this->category === '') {
            return [];
        }

        $id = (int) $this->category;

        return Category::query()->where('id', $id)->orWhere('parent_id', $id)->pluck('id')->all();
    }
}
