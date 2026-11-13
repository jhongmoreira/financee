<?php

namespace App\Livewire;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\Transfer;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Formulário único de lançamento (despesa, receita, transferência), aberto pelo
 * botão "+" de qualquer tela ou ao tocar num lançamento existente.
 * Eventos: open-entry {kind}, edit-entry {kind, id}. Ao salvar: entry-saved.
 */
class EntryForm extends Component
{
    public string $kind = 'expense'; // expense | income | transfer

    #[Locked]
    public ?int $editingId = null;

    public int $amount = 0;

    public string $description = '';

    public ?string $category_id = null;

    public ?string $account_id = null;

    public ?string $to_account_id = null;

    public string $date = '';

    public bool $paid = true;

    public string $notes = '';

    public bool $showMore = false;

    public function mount(): void
    {
        $this->date = today()->toDateString();
    }

    #[On('open-entry')]
    public function open(string $kind = 'expense', ?string $date = null): void
    {
        $this->resetValidation();
        $this->reset('editingId', 'amount', 'description', 'category_id', 'to_account_id', 'notes', 'showMore');
        $this->kind = in_array($kind, ['expense', 'income', 'transfer'], true) ? $kind : 'expense';
        $this->date = $date ?: today()->toDateString();
        $this->paid = $this->date <= today()->toDateString();
        $this->account_id ??= $this->defaultAccountId();
    }

    #[On('edit-entry')]
    public function edit(string $kind, int $id): void
    {
        $this->resetValidation();
        $this->showMore = false;

        if ($kind === 'transfer') {
            $transfer = Transfer::findOrFail($id);
            $this->kind = 'transfer';
            $this->editingId = $transfer->id;
            $this->amount = $transfer->amount;
            $this->description = (string) $transfer->description;
            $this->account_id = (string) $transfer->from_account_id;
            $this->to_account_id = (string) $transfer->to_account_id;
            $this->date = $transfer->date->toDateString();
            $this->paid = $transfer->isPaid();
            $this->notes = '';
        } else {
            $transaction = Transaction::findOrFail($id);
            $this->kind = $transaction->type;
            $this->editingId = $transaction->id;
            $this->amount = $transaction->amount;
            $this->description = $transaction->description;
            $this->category_id = $transaction->category_id ? (string) $transaction->category_id : null;
            $this->account_id = $transaction->account_id ? (string) $transaction->account_id : null;
            $this->date = $transaction->date->toDateString();
            $this->paid = $transaction->isPaid();
            $this->notes = (string) $transaction->notes;
            $this->showMore = $this->notes !== '';
        }

        $this->dispatch('open-sheet', 'entry-form');
    }

    public function updatedKind(): void
    {
        if ($this->editingId) {
            return; // tipo não muda na edição
        }
        $this->category_id = null;
        $this->resetValidation();
    }

    public function updatedDate(string $value): void
    {
        $this->paid = $value <= today()->toDateString();
    }

    /** Ao escolher uma descrição já usada, sugere a mesma categoria e conta. */
    public function updatedDescription(string $value): void
    {
        if ($this->editingId || $this->kind === 'transfer' || trim($value) === '') {
            return;
        }

        $last = Transaction::query()->where('type', $this->kind)->where('description', trim($value))
            ->latest('date')->latest('id')->first();

        if ($last) {
            $this->category_id = $last->category_id ? (string) $last->category_id : $this->category_id;
            $this->account_id = $last->account_id ? (string) $last->account_id : $this->account_id;
        }
    }

    public function setDate(string $which): void
    {
        $this->date = match ($which) {
            'yesterday' => today()->subDay()->toDateString(),
            default => today()->toDateString(),
        };
        $this->updatedDate($this->date);
    }

    public function save(bool $another = false): void
    {
        $this->kind === 'transfer' ? $this->saveTransfer() : $this->saveTransaction();

        $this->dispatch('entry-saved');
        $this->dispatch('toast', message: $this->editingId ? 'Lançamento atualizado.' : 'Lançamento salvo.');

        if ($another) {
            $this->reset('editingId', 'amount', 'description', 'notes');
            $this->resetValidation();

            return;
        }

        $this->dispatch('close-sheet', 'entry-form');
        $this->reset('editingId');
    }

    public function delete(): void
    {
        if ($this->editingId) {
            $this->kind === 'transfer'
                ? Transfer::findOrFail($this->editingId)->delete()
                : Transaction::findOrFail($this->editingId)->delete();
        }

        $this->dispatch('entry-saved');
        $this->dispatch('close-sheet', 'entry-form');
        $this->dispatch('toast', message: 'Lançamento excluído.');
        $this->reset('editingId');
    }

    public function render(): View
    {
        $accounts = Account::query()->active()->ordered()
            ->when($this->account_id, fn ($q) => $q->orWhere('id', $this->account_id)) // conta arquivada em edição
            ->pluck('name', 'id')->all();

        return view('livewire.entry-form', [
            'accounts' => $accounts,
            'categoryOptions' => $this->kind === 'transfer' ? [] : $this->categoryOptions(),
            'recentCategories' => $this->kind === 'transfer' ? collect() : $this->recentCategories(),
            'suggestions' => $this->kind === 'transfer' ? [] : Transaction::query()->where('type', $this->kind)
                ->where('is_adjustment', false)->latest('id')->limit(200)->pluck('description')->unique()->take(40)->values()->all(),
            'isToday' => $this->date === today()->toDateString(),
            'isYesterday' => $this->date === today()->subDay()->toDateString(),
        ]);
    }

    private function saveTransaction(): void
    {
        $data = $this->validate([
            'amount' => ['required', 'integer', 'min:1', 'max:100000000000'],
            'description' => ['required', 'string', 'max:120'],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->where('user_id', Auth::id())->where('type', $this->kind)],
            'account_id' => ['required', 'integer', Rule::exists('accounts', 'id')->where('user_id', Auth::id())],
            'date' => ['required', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], attributes: ['account_id' => 'conta', 'category_id' => 'categoria', 'amount' => 'valor']);

        $data['type'] = $this->kind;
        $data['description'] = trim($data['description']);
        $data['paid_at'] = $this->paidAt();
        $data['notes'] = $data['notes'] ?: null;

        if ($this->editingId) {
            $transaction = Transaction::findOrFail($this->editingId);
            $data['type'] = $transaction->type;
            $transaction->update($data);
        } else {
            Transaction::create($data);
        }
    }

    private function saveTransfer(): void
    {
        $data = $this->validate([
            'amount' => ['required', 'integer', 'min:1', 'max:100000000000'],
            'account_id' => ['required', 'integer', Rule::exists('accounts', 'id')->where('user_id', Auth::id())],
            'to_account_id' => ['required', 'integer', 'different:account_id', Rule::exists('accounts', 'id')->where('user_id', Auth::id())],
            'date' => ['required', 'date_format:Y-m-d'],
            'description' => ['nullable', 'string', 'max:120'],
        ], attributes: ['account_id' => 'conta de origem', 'to_account_id' => 'conta de destino', 'amount' => 'valor']);

        $payload = [
            'from_account_id' => $data['account_id'],
            'to_account_id' => $data['to_account_id'],
            'amount' => $data['amount'],
            'date' => $data['date'],
            'paid_at' => $this->paidAt(),
            'description' => trim((string) $data['description']) ?: null,
        ];

        $this->editingId
            ? Transfer::findOrFail($this->editingId)->update($payload)
            : Transfer::create($payload);
    }

    /** Efetivado na própria data do lançamento (ou hoje, se a data é futura e foi marcado como pago). */
    private function paidAt(): ?string
    {
        if (! $this->paid) {
            return null;
        }

        return min($this->date, today()->toDateString());
    }

    private function defaultAccountId(): ?string
    {
        $last = Transaction::query()->whereNotNull('account_id')->latest('id')->value('account_id');
        $id = $last && Account::query()->active()->whereKey($last)->exists()
            ? $last
            : Account::query()->active()->ordered()->value('id');

        return $id ? (string) $id : null;
    }

    /** @return array<string, array<int, string>> optgroup => [id => nome] */
    private function categoryOptions(): array
    {
        $options = [];
        $roots = Category::query()->ofType($this->kind)->roots()->with('children')
            ->orderBy('sort_order')->orderBy('name')->get();

        foreach ($roots as $root) {
            $options[$root->name] = [$root->id => $root->name.' (geral)']
                + $root->children->pluck('name', 'id')->all();
        }

        return $options;
    }

    /** @return Collection<int, Category> categorias usadas recentemente */
    private function recentCategories(): Collection
    {
        $ids = Transaction::query()->where('type', $this->kind)->whereNotNull('category_id')
            ->latest('id')->limit(60)->pluck('category_id')->unique()->take(6);

        if ($ids->isEmpty()) {
            $ids = Category::query()->ofType($this->kind)->roots()->orderBy('sort_order')->limit(6)->pluck('id');
        }

        $categories = Category::query()->whereIn('id', $ids)->get()->keyBy('id');

        return $ids->map(fn ($id) => $categories->get($id))->filter()->values();
    }
}
