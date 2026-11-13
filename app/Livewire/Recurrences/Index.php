<?php

namespace App\Livewire\Recurrences;

use App\Models\Account;
use App\Models\Category;
use App\Models\Recurrence;
use App\Models\Transaction;
use App\Services\RecurrenceService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Lançamentos fixos')]
class Index extends Component
{
    public bool $showEnded = false;

    #[Locked]
    public ?int $editingId = null;

    public string $description = '';

    public int $amount = 0;

    public ?string $category_id = null;

    public ?string $account_id = null;

    public string $frequency = 'monthly';

    public ?string $end_date = null;

    public bool $auto_mark_paid = false;

    #[On('entry-saved')]
    public function refresh(): void {}

    public function edit(int $id): void
    {
        $r = Recurrence::findOrFail($id);

        $this->resetValidation();
        $this->editingId = $r->id;
        $this->description = $r->description;
        $this->amount = $r->amount;
        $this->category_id = $r->category_id ? (string) $r->category_id : null;
        $this->account_id = $r->account_id ? (string) $r->account_id : null;
        $this->frequency = $r->frequency;
        $this->end_date = $r->end_date?->toDateString();
        $this->auto_mark_paid = $r->auto_mark_paid;

        $this->dispatch('open-sheet', 'recurrence-form');
    }

    /** Salva e regera os lançamentos pendentes a partir de hoje (os já pagos não mudam). */
    public function save(RecurrenceService $service): void
    {
        $r = Recurrence::findOrFail($this->editingId);

        $data = $this->validate([
            'description' => ['required', 'string', 'max:120'],
            'amount' => ['required', 'integer', 'min:1', 'max:100000000000'],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->where('user_id', Auth::id())->where('type', $r->type)],
            'account_id' => [$r->credit_card_id ? 'nullable' : 'required', 'nullable', 'integer', Rule::exists('accounts', 'id')->where('user_id', Auth::id())],
            'frequency' => ['required', Rule::in(array_keys(Recurrence::FREQUENCIES))],
            'end_date' => ['nullable', 'date_format:Y-m-d'],
            'auto_mark_paid' => ['boolean'],
        ], attributes: ['account_id' => 'conta', 'end_date' => 'data final']);

        if ($r->credit_card_id) {
            unset($data['account_id']);
        }
        $data['end_date'] = $data['end_date'] ?: null;

        $service->updateFrom($r, CarbonImmutable::today(), $data);

        $this->dispatch('close-sheet', 'recurrence-form');
        $this->dispatch('entry-saved');
        $this->dispatch('toast', message: 'Lançamento fixo atualizado (a partir de hoje).');
    }

    public function stop(RecurrenceService $service): void
    {
        $service->stop(Recurrence::findOrFail($this->editingId), CarbonImmutable::today());

        $this->dispatch('close-sheet', 'recurrence-form');
        $this->dispatch('entry-saved');
        $this->dispatch('toast', message: 'Lançamento fixo encerrado. Os previstos futuros foram removidos.');
    }

    public function render(): View
    {
        $recurrences = Recurrence::query()->with(['category', 'account', 'creditCard'])
            ->when(! $this->showEnded, fn ($q) => $q->active())
            ->orderBy('type')->orderBy('description')->get();

        $next = Transaction::query()->whereIn('recurrence_id', $recurrences->pluck('id'))
            ->where('date', '>=', today()->toDateString())
            ->selectRaw('recurrence_id, MIN(date) AS next_date')->groupBy('recurrence_id')->pluck('next_date', 'recurrence_id');

        $active = $recurrences->filter(fn (Recurrence $r) => ! $r->end_date || $r->end_date->greaterThanOrEqualTo(today()));
        $editing = $this->editingId ? Recurrence::find($this->editingId) : null;

        return view('livewire.recurrences.index', [
            'recurrences' => $recurrences,
            'next' => $next,
            'monthlyExpense' => $active->where('type', 'expense')->sum(fn (Recurrence $r) => $r->monthlyAmount()),
            'monthlyIncome' => $active->where('type', 'income')->sum(fn (Recurrence $r) => $r->monthlyAmount()),
            'editing' => $editing,
            'accounts' => Account::query()->active()->ordered()->pluck('name', 'id')->all(),
            'categories' => $editing ? Category::query()->ofType($editing->type)->orderBy('parent_id')->orderBy('name')->pluck('name', 'id')->all() : [],
        ]);
    }
}
