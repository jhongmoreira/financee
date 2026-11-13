<?php

namespace App\Livewire\Accounts;

use App\Models\Account;
use App\Models\Institution;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * @property-read Collection<int, Account> $accounts
 * @property-read int $total
 */
#[Title('Contas')]
class Index extends Component
{
    public bool $showArchived = false;

    #[Locked]
    public ?int $editingId = null;

    public string $name = '';

    public string $type = 'checking';

    public ?string $institution_id = null;

    public int $initial_balance = 0;

    public string $color = '#059669';

    public bool $include_in_total = true;

    public function create(): void
    {
        $this->resetForm();
        $this->dispatch('open-sheet', 'account-form');
    }

    public function edit(int $id): void
    {
        $account = Account::findOrFail($id);

        $this->resetValidation();
        $this->editingId = $account->id;
        $this->name = $account->name;
        $this->type = $account->type;
        $this->institution_id = $account->institution_id ? (string) $account->institution_id : null;
        $this->initial_balance = $account->initial_balance;
        $this->color = $account->color;
        $this->include_in_total = $account->include_in_total;

        $this->dispatch('open-sheet', 'account-form');
    }

    /** Ao escolher a instituição, sugere nome e cor (se o usuário ainda não definiu). */
    public function updatedInstitutionId(?string $value): void
    {
        $institution = $value ? Institution::find($value) : null;

        if ($institution && $institution->type !== 'other') {
            if ($this->name === '') {
                $this->name = $institution->name;
            }
            $this->color = $institution->color;
        }
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:60'],
            'type' => ['required', Rule::in(array_keys(Account::TYPES))],
            'institution_id' => ['nullable', 'integer', 'exists:institutions,id'],
            'initial_balance' => ['required', 'integer', 'between:-100000000000,100000000000'],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'include_in_total' => ['boolean'],
        ]);

        if ($this->editingId) {
            Account::findOrFail($this->editingId)->update($data);
            $message = 'Conta atualizada.';
        } else {
            Account::create($data);
            $message = 'Conta criada.';
        }

        $this->dispatch('close-sheet', 'account-form');
        $this->dispatch('toast', message: $message);
        $this->resetForm();
    }

    public function toggleArchive(): void
    {
        $account = Account::findOrFail($this->editingId);
        $account->update(['archived_at' => $account->archived_at ? null : now()]);

        $this->dispatch('close-sheet', 'account-form');
        $this->dispatch('toast', message: $account->archived_at ? 'Conta arquivada.' : 'Conta reativada.');
        $this->resetForm();
    }

    public function delete(): void
    {
        Account::findOrFail($this->editingId)->delete();

        $this->dispatch('close-sheet', 'account-form');
        $this->dispatch('toast', message: 'Conta excluída.');
        $this->resetForm();
    }

    /** @return Collection<int, Account> */
    #[Computed]
    public function accounts(): Collection
    {
        return Account::query()->with('institution')
            ->when(! $this->showArchived, fn ($q) => $q->active())
            ->ordered()->get();
    }

    #[Computed]
    public function total(): int
    {
        return $this->accounts->filter(fn (Account $a) => $a->include_in_total && ! $a->archived_at)->sum(fn (Account $a) => $a->balance());
    }

    public function render(): View
    {
        return view('livewire.accounts.index', [
            'institutions' => Institution::options('bank'),
            'editing' => $this->editingId ? Account::find($this->editingId) : null,
        ]);
    }

    private function resetForm(): void
    {
        $this->resetValidation();
        $this->reset('editingId', 'name', 'type', 'institution_id', 'initial_balance', 'color', 'include_in_total');
    }
}
