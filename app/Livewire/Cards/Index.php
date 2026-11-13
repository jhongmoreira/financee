<?php

namespace App\Livewire\Cards;

use App\Models\Account;
use App\Models\CreditCard;
use App\Models\Institution;
use App\Services\InvoiceService;
use App\Support\Reorder;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * @property-read Collection<int, CreditCard> $cards
 */
#[Title('Cartões de crédito')]
class Index extends Component
{
    public bool $showArchived = false;

    public bool $reordering = false;

    #[Locked]
    public ?int $editingId = null;

    public string $name = '';

    public ?string $institution_id = null;

    public string $brand = 'mastercard';

    public int $limit = 0;

    public int $closing_day = 1;

    public int $due_day = 10;

    public ?string $payment_account_id = null;

    public string $color = '#7c3aed';

    public function move(int $id, int $direction): void
    {
        Reorder::move(CreditCard::query()->active()->ordered(), $id, $direction);
        unset($this->cards);
    }

    public function create(): void
    {
        $this->resetForm();
        $this->payment_account_id = (string) (Account::active()->ordered()->value('id') ?? '') ?: null;
        $this->dispatch('open-sheet', 'card-form');
    }

    public function edit(int $id): void
    {
        $card = CreditCard::findOrFail($id);

        $this->resetValidation();
        $this->editingId = $card->id;
        $this->name = $card->name;
        $this->institution_id = $card->institution_id ? (string) $card->institution_id : null;
        $this->brand = $card->brand;
        $this->limit = $card->limit;
        $this->closing_day = $card->closing_day;
        $this->due_day = $card->due_day;
        $this->payment_account_id = $card->payment_account_id ? (string) $card->payment_account_id : null;
        $this->color = $card->color;

        $this->dispatch('open-sheet', 'card-form');
    }

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
            'institution_id' => ['nullable', 'integer', 'exists:institutions,id'],
            'brand' => ['required', Rule::in(array_keys(CreditCard::BRANDS))],
            'limit' => ['required', 'integer', 'between:0,100000000000'],
            'closing_day' => ['required', 'integer', 'between:1,31'],
            'due_day' => ['required', 'integer', 'between:1,31'],
            'payment_account_id' => ['nullable', 'integer', Rule::exists('accounts', 'id')->where('user_id', Auth::id())],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        if ($this->editingId) {
            CreditCard::findOrFail($this->editingId)->update($data);
            $message = 'Cartão atualizado.';
        } else {
            CreditCard::create($data);
            $message = 'Cartão criado.';
        }

        $this->dispatch('close-sheet', 'card-form');
        $this->dispatch('toast', message: $message);
        $this->resetForm();
    }

    public function toggleArchive(): void
    {
        $card = CreditCard::findOrFail($this->editingId);
        $card->update(['archived_at' => $card->archived_at ? null : now()]);

        $this->dispatch('close-sheet', 'card-form');
        $this->dispatch('toast', message: $card->archived_at ? 'Cartão arquivado.' : 'Cartão reativado.');
        $this->resetForm();
    }

    public function delete(): void
    {
        $card = CreditCard::findOrFail($this->editingId);

        if ($card->transactions()->exists()) {
            $this->dispatch('toast', message: 'Este cartão tem lançamentos. Arquive-o em vez de excluir.', type: 'error');

            return;
        }

        $card->delete();

        $this->dispatch('close-sheet', 'card-form');
        $this->dispatch('toast', message: 'Cartão excluído.');
        $this->resetForm();
    }

    /** @return Collection<int, CreditCard> */
    #[Computed]
    public function cards(): Collection
    {
        return CreditCard::query()->with(['institution', 'paymentAccount'])
            ->when(! $this->showArchived, fn ($q) => $q->active())
            ->ordered()->get();
    }

    #[On('entry-saved')]
    public function refresh(): void {}

    public function render(InvoiceService $invoices): View
    {
        $current = $this->cards->mapWithKeys(fn (CreditCard $card) => [$card->id => $invoices->currentInvoice($card)]);
        $summaries = $invoices->summaries($current->values());

        return view('livewire.cards.index', [
            'current' => $current,
            'summaries' => $summaries,
            'institutions' => Institution::options('bank'),
            'accounts' => Account::active()->ordered()->pluck('name', 'id')->all(),
            'days' => array_combine(range(1, 31), range(1, 31)),
            'editing' => $this->editingId ? CreditCard::find($this->editingId) : null,
        ]);
    }

    private function resetForm(): void
    {
        $this->resetValidation();
        $this->reset('editingId', 'name', 'institution_id', 'brand', 'limit', 'closing_day', 'due_day', 'payment_account_id', 'color');
    }
}
