<?php

namespace App\Livewire;

use App\Models\Account;
use App\Models\Category;
use App\Models\CreditCard;
use App\Models\InstallmentPlan;
use App\Models\Recurrence;
use App\Models\Transaction;
use App\Models\Transfer;
use App\Services\InvoiceService;
use App\Services\RecurrenceService;
use App\Support\Month;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Formulário único de lançamento (despesa, receita, compra no cartão, transferência),
 * aberto pelo "+" de qualquer tela ou ao tocar num lançamento existente.
 * Eventos: open-entry {kind}, edit-entry {kind, id}. Ao salvar: entry-saved.
 */
class EntryForm extends Component
{
    public const KINDS = ['expense', 'income', 'card', 'transfer'];

    public string $kind = 'expense';

    #[Locked]
    public ?int $editingId = null;

    #[Locked]
    public ?int $planId = null; // parcela de um parcelamento em edição

    public int $amount = 0;

    public string $description = '';

    public ?string $category_id = null;

    public ?string $account_id = null;

    public ?string $to_account_id = null;

    public string $date = '';

    public bool $paid = true;

    public string $notes = '';

    public bool $showMore = false;

    // Cartão
    public ?string $credit_card_id = null;

    public int $installments = 1;

    public string $installment_mode = 'total'; // total | parcel

    public bool $is_refund = false;

    public ?string $invoice_ref = null;

    public string $scope = 'this'; // this | future | all (edição de parcelas/fixos)

    // Repetição (novo lançamento)
    public string $repeat = 'none'; // none | fixed | installments

    public string $frequency = 'monthly';

    public ?string $end_date = null;

    public bool $auto_mark_paid = false;

    #[Locked]
    public ?int $recurrenceId = null; // lançamento fixo em edição

    public function mount(): void
    {
        $this->date = today()->toDateString();
    }

    #[On('open-entry')]
    public function open(string $kind = 'expense', ?string $date = null, ?string $card = null, ?string $repeat = null): void
    {
        $this->resetValidation();
        $this->reset('editingId', 'planId', 'recurrenceId', 'amount', 'description', 'category_id', 'to_account_id', 'notes', 'showMore',
            'installments', 'installment_mode', 'is_refund', 'scope', 'repeat', 'frequency', 'end_date', 'auto_mark_paid');
        $this->kind = in_array($kind, self::KINDS, true) ? $kind : 'expense';
        $this->date = $date ?: today()->toDateString();
        $this->paid = $this->date <= today()->toDateString();
        $this->account_id ??= $this->defaultAccountId();
        $this->credit_card_id = $card ?: ($this->credit_card_id ?? $this->defaultCardId());
        $this->invoice_ref = $this->defaultInvoiceRef();
        $this->repeat = in_array($repeat, ['fixed', 'installments'], true) ? $repeat : 'none';
    }

    #[On('edit-entry')]
    public function edit(string $kind, int $id): void
    {
        $this->resetValidation();
        $this->reset('planId', 'recurrenceId', 'showMore', 'installments', 'installment_mode', 'is_refund', 'scope', 'to_account_id', 'category_id', 'repeat');

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
            $transaction = Transaction::with('invoice')->findOrFail($id);
            $this->editingId = $transaction->id;
            $this->amount = $transaction->amount;
            $this->description = $transaction->description;
            $this->category_id = $transaction->category_id ? (string) $transaction->category_id : null;
            $this->date = $transaction->date->toDateString();
            $this->paid = $transaction->isPaid();
            $this->notes = (string) $transaction->notes;
            $this->showMore = $this->notes !== '';
            $this->recurrenceId = $transaction->recurrence_id;
            $this->planId = $transaction->installment_plan_id;

            if ($transaction->isCard()) {
                $this->kind = 'card';
                $this->credit_card_id = (string) $transaction->credit_card_id;
                $this->is_refund = $transaction->type === 'income';
                $this->invoice_ref = $transaction->invoice?->reference_month;
            } else {
                $this->kind = $transaction->type;
                $this->account_id = $transaction->account_id ? (string) $transaction->account_id : null;
            }
        }

        $this->dispatch('open-sheet', 'entry-form');
    }

    public function updatedKind(): void
    {
        if (! in_array($this->kind, self::KINDS, true)) {
            $this->kind = 'expense';
        }

        if ($this->editingId) {
            return;
        }
        $this->category_id = null;
        $this->is_refund = false;
        $this->invoice_ref = $this->defaultInvoiceRef();
        $this->resetValidation();
    }

    public function updatedDate(string $value): void
    {
        $this->paid = $value <= today()->toDateString();
        if (! $this->planId) {
            $this->invoice_ref = $this->defaultInvoiceRef();
        }
    }

    public function updatedCreditCardId(): void
    {
        $this->invoice_ref = $this->defaultInvoiceRef();
    }

    public function updatedRepeat(string $value): void
    {
        if ($value === 'installments' && $this->installments < 2) {
            $this->installments = 2;
        }
        if ($value !== 'installments' && $this->kind !== 'card') {
            $this->installments = 1;
        }
    }

    public function updatedIsRefund(): void
    {
        $this->category_id = null;
        $this->installments = 1;
    }

    /** Ao escolher uma descrição já usada, sugere a mesma categoria e conta/cartão. */
    public function updatedDescription(string $value): void
    {
        if ($this->editingId || $this->kind === 'transfer' || trim($value) === '') {
            return;
        }

        $last = Transaction::query()->where('type', $this->transactionType())->where('description', trim($value))
            ->when($this->kind === 'card', fn ($q) => $q->whereNotNull('credit_card_id'), fn ($q) => $q->whereNotNull('account_id'))
            ->latest('date')->latest('id')->first();

        if ($last) {
            $this->category_id = $last->category_id ? (string) $last->category_id : $this->category_id;
            if ($this->kind === 'card') {
                $this->credit_card_id = (string) $last->credit_card_id;
                $this->invoice_ref = $this->defaultInvoiceRef();
            } else {
                $this->account_id = $last->account_id ? (string) $last->account_id : $this->account_id;
            }
        }
    }

    public function setDate(string $which): void
    {
        $this->date = $which === 'yesterday' ? today()->subDay()->toDateString() : today()->toDateString();
        $this->updatedDate($this->date);
    }

    public function save(bool $another = false): void
    {
        if (! in_array($this->kind, self::KINDS, true)) {
            $this->kind = 'expense';
        }

        match ($this->kind) {
            'transfer' => $this->saveTransfer(),
            'card' => $this->saveCard(),
            default => $this->saveTransaction(),
        };

        $this->dispatch('entry-saved');
        $this->dispatch('toast', message: $this->editingId ? 'Lançamento atualizado.' : 'Lançamento salvo.');

        if ($another) {
            $this->reset('editingId', 'planId', 'recurrenceId', 'amount', 'description', 'notes', 'installments', 'is_refund', 'repeat', 'end_date');
            $this->resetValidation();

            return;
        }

        $this->dispatch('close-sheet', 'entry-form');
        $this->reset('editingId', 'planId', 'recurrenceId');
    }

    public function delete(): void
    {
        if ($this->editingId) {
            if ($this->kind === 'transfer') {
                Transfer::findOrFail($this->editingId)->delete();
            } elseif ($this->recurrenceId && $this->scope === 'future') {
                $transaction = Transaction::findOrFail($this->editingId);
                $recurrence = Recurrence::findOrFail($this->recurrenceId);
                $transaction->delete();
                app(RecurrenceService::class)->stop($recurrence, $transaction->date);
            } else {
                $this->scopedQuery()->get()->each->delete();
                $this->cleanupPlan();
            }
        }

        $this->dispatch('entry-saved');
        $this->dispatch('close-sheet', 'entry-form');
        $this->dispatch('toast', message: 'Lançamento excluído.');
        $this->reset('editingId', 'planId');
    }

    /** Antecipa as parcelas futuras deste parcelamento para a fatura atual. */
    public function anticipate(InvoiceService $invoices): void
    {
        $plan = InstallmentPlan::findOrFail($this->planId);
        $moved = $invoices->anticipate($plan);

        $this->dispatch('entry-saved');
        $this->dispatch('close-sheet', 'entry-form');
        $this->dispatch('toast', message: $moved ? "{$moved} parcela(s) antecipada(s) para a fatura atual." : 'Não há parcelas futuras para antecipar.');
        $this->reset('editingId', 'planId');
    }

    public function render(InvoiceService $invoices): View
    {
        $accounts = Account::query()->active()->ordered()
            ->when($this->account_id, fn ($q) => $q->orWhere('id', $this->account_id))
            ->pluck('name', 'id')->all();

        $cards = CreditCard::query()->active()->ordered()
            ->when($this->credit_card_id, fn ($q) => $q->orWhere('id', $this->credit_card_id))
            ->pluck('name', 'id')->all();

        $hasCategories = $this->kind !== 'transfer';

        return view('livewire.entry-form', [
            'accounts' => $accounts,
            'cards' => $cards,
            'categoryOptions' => $hasCategories ? $this->categoryOptions() : [],
            'recentCategories' => $hasCategories ? $this->recentCategories() : collect(),
            'suggestions' => $hasCategories ? Transaction::query()->where('type', $this->transactionType())
                ->where('is_adjustment', false)->whereNull('installment_number')->latest('id')->limit(200)
                ->pluck('description')->unique()->take(40)->values()->all() : [],
            'invoiceOptions' => $this->kind === 'card' ? $this->invoiceOptions($invoices) : [],
            'plan' => $this->planId ? InstallmentPlan::find($this->planId) : null,
            'recurrence' => $this->recurrenceId ? Recurrence::find($this->recurrenceId) : null,
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

            if ($this->planId && $this->scope !== 'this') {
                // Parcelamento em conta: descrição/categoria/valor/conta no escopo; data e situação só nesta.
                $common = array_intersect_key($data, array_flip(['description', 'category_id', 'amount', 'account_id', 'notes']));
                $this->scopedQuery()->get()->each->update($common);
                $transaction->update(['date' => $data['date'], 'paid_at' => $data['paid_at']]);

                return;
            }

            $transaction->update($data);

            if ($this->recurrenceId && $this->scope === 'future') {
                app(RecurrenceService::class)->updateFrom(Recurrence::findOrFail($this->recurrenceId), $transaction->date->addDay(), [
                    'description' => $data['description'], 'amount' => $data['amount'],
                    'category_id' => $data['category_id'], 'account_id' => $data['account_id'],
                ]);
            }

            return;
        }

        if ($this->repeat === 'fixed') {
            $this->createRecurrence($data + ['account_id' => $data['account_id']]);

            return;
        }

        if ($this->repeat === 'installments' && $this->installments > 1) {
            $this->validate(['installments' => ['integer', 'between:2,360'], 'installment_mode' => [Rule::in(['total', 'parcel'])]]);
            $total = $this->installment_mode === 'parcel' ? $data['amount'] * $this->installments : $data['amount'];
            app(RecurrenceService::class)->createAccountInstallments($this->kind, $data['description'], $total, $this->installments,
                CarbonImmutable::parse($data['date']), (int) $data['account_id'], $data['category_id'], $data['paid_at'] !== null, $data['notes']);

            return;
        }

        Transaction::create($data);
    }

    /** @param array<string, mixed> $data */
    private function createRecurrence(array $data): void
    {
        $this->validate([
            'frequency' => ['required', Rule::in(array_keys(Recurrence::FREQUENCIES))],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date'],
        ], attributes: ['end_date' => 'data final']);

        $recurrence = Recurrence::create([
            'type' => $data['type'],
            'description' => $data['description'],
            'amount' => $data['amount'],
            'category_id' => $data['category_id'] ?? null,
            'account_id' => $data['account_id'] ?? null,
            'credit_card_id' => $data['credit_card_id'] ?? null,
            'frequency' => $this->frequency,
            'start_date' => $data['date'],
            'end_date' => $this->end_date ?: null,
            'auto_mark_paid' => $this->auto_mark_paid,
        ]);

        app(RecurrenceService::class)->generate($recurrence);

        // A 1ª ocorrência segue o "Pago" do formulário.
        if (($data['paid_at'] ?? null) && ! $recurrence->credit_card_id) {
            Transaction::query()->where('recurrence_id', $recurrence->id)->whereDate('date', $data['date'])
                ->update(['paid_at' => $data['paid_at']]);
        }
    }

    private function saveCard(): void
    {
        $data = $this->validate([
            'amount' => ['required', 'integer', 'min:1', 'max:100000000000'],
            'description' => ['required', 'string', 'max:120'],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->where('user_id', Auth::id())->where('type', $this->transactionType())],
            'credit_card_id' => ['required', 'integer', Rule::exists('credit_cards', 'id')->where('user_id', Auth::id())],
            'date' => ['required', 'date_format:Y-m-d'],
            'installments' => ['required', 'integer', 'between:1,72'],
            'installment_mode' => ['required', Rule::in(['total', 'parcel'])],
            'invoice_ref' => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], attributes: ['credit_card_id' => 'cartão', 'category_id' => 'categoria', 'amount' => 'valor', 'invoice_ref' => 'fatura']);

        $card = CreditCard::findOrFail($data['credit_card_id']);
        $invoices = app(InvoiceService::class);

        if (! $this->editingId && $this->repeat === 'fixed' && ! $this->is_refund) {
            $this->createRecurrence([
                'type' => 'expense', 'description' => trim($data['description']), 'amount' => $data['amount'],
                'category_id' => $data['category_id'], 'credit_card_id' => $card->id, 'date' => $data['date'],
            ]);

            return;
        }

        if (! $this->editingId) {
            $total = $this->installment_mode === 'parcel' ? $data['amount'] * $data['installments'] : $data['amount'];
            $invoices->createPurchase($card, trim($data['description']), $data['category_id'], CarbonImmutable::parse($data['date']),
                $total, $data['installments'], $this->is_refund, $data['invoice_ref'], $data['notes'] ?: null);

            return;
        }

        $common = [
            'description' => trim($data['description']),
            'category_id' => $data['category_id'],
            'amount' => $data['amount'],
            'notes' => $data['notes'] ?: null,
        ];

        if ($this->planId) {
            // Parcelas: altera descrição/categoria/valor no escopo escolhido; datas e faturas ficam.
            $this->scopedQuery()->get()->each->update($common);
            InstallmentPlan::whereKey($this->planId)->update(['description' => $common['description'], 'category_id' => $common['category_id']]);

            return;
        }

        if ($this->recurrenceId && $this->scope === 'future') {
            $transaction = Transaction::findOrFail($this->editingId);
            $transaction->update($common);
            app(RecurrenceService::class)->updateFrom(Recurrence::findOrFail($this->recurrenceId), $transaction->date->addDay(), [
                'description' => $common['description'], 'amount' => $common['amount'], 'category_id' => $common['category_id'],
            ]);

            return;
        }

        $invoice = $invoices->byReference($card, $data['invoice_ref']);
        Transaction::findOrFail($this->editingId)->update($common + [
            'credit_card_id' => $card->id,
            'invoice_id' => $invoice->id,
            'date' => $data['date'],
            'paid_at' => $data['date'],
        ]);
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

    /**
     * Lançamentos afetados pela edição/exclusão (parcela única ou escopo do parcelamento).
     *
     * @return Builder<Transaction>
     */
    private function scopedQuery(): Builder
    {
        $current = Transaction::findOrFail($this->editingId);

        if (! $this->planId || $this->scope === 'this') {
            return Transaction::query()->whereKey($current->id);
        }

        return Transaction::query()->where('installment_plan_id', $this->planId)
            ->when($this->scope === 'future', fn ($q) => $q->where('installment_number', '>=', $current->installment_number));
    }

    /** Remove o plano de parcelamento se não sobrou nenhuma parcela. */
    private function cleanupPlan(): void
    {
        if ($this->planId && ! Transaction::query()->where('installment_plan_id', $this->planId)->exists()) {
            InstallmentPlan::whereKey($this->planId)->delete();
        }
    }

    private function transactionType(): string
    {
        return match ($this->kind) {
            'income' => 'income',
            'card' => $this->is_refund ? 'income' : 'expense',
            default => 'expense',
        };
    }

    private function paidAt(): ?string
    {
        return $this->paid ? min($this->date, today()->toDateString()) : null;
    }

    private function defaultAccountId(): ?string
    {
        $last = Transaction::query()->whereNotNull('account_id')->latest('id')->value('account_id');
        $id = $last && Account::query()->active()->whereKey($last)->exists()
            ? $last
            : Account::query()->active()->ordered()->value('id');

        return $id ? (string) $id : null;
    }

    private function defaultCardId(): ?string
    {
        $last = Transaction::query()->whereNotNull('credit_card_id')->latest('id')->value('credit_card_id');
        $id = $last && CreditCard::query()->active()->whereKey($last)->exists()
            ? $last
            : CreditCard::query()->active()->ordered()->value('id');

        return $id ? (string) $id : null;
    }

    private function defaultInvoiceRef(): ?string
    {
        $card = $this->credit_card_id ? CreditCard::find($this->credit_card_id) : null;

        if (! $card || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->date)) {
            return null;
        }

        return app(InvoiceService::class)->invoiceFor($card, CarbonImmutable::parse($this->date))->reference_month;
    }

    /** @return array<string, string> "YYYY-MM" => "Dez/26 (10/12)" (vencimento) */
    private function invoiceOptions(InvoiceService $invoices): array
    {
        $card = $this->credit_card_id ? CreditCard::find($this->credit_card_id) : null;
        $base = $this->invoice_ref ?: $this->defaultInvoiceRef();

        if (! $card || ! $base) {
            return [];
        }

        $options = [];
        foreach (range(-2, 3) as $offset) {
            $invoice = $invoices->byReference($card, Month::parse($base)->addMonthsNoOverflow($offset));
            $options[$invoice->reference_month] = $invoice->shortLabel().' ('.$invoice->due_date->format('d/m').')';
        }

        return $options;
    }

    /** @return array<string, array<int, string>> optgroup => [id => nome] */
    private function categoryOptions(): array
    {
        $options = [];
        $roots = Category::query()->ofType($this->transactionType())->roots()->with('children')
            ->orderBy('sort_order')->orderBy('name')->get();

        foreach ($roots as $root) {
            $options[$root->name] = [$root->id => $root->name.' (geral)'] + $root->children->pluck('name', 'id')->all();
        }

        return $options;
    }

    /** @return Collection<int, Category> categorias usadas recentemente */
    private function recentCategories(): Collection
    {
        $type = $this->transactionType();
        $ids = Transaction::query()->where('type', $type)->whereNotNull('category_id')
            ->latest('id')->limit(60)->pluck('category_id')->unique()->take(6);

        if ($ids->isEmpty()) {
            $ids = Category::query()->ofType($type)->roots()->orderBy('sort_order')->limit(6)->pluck('id');
        }

        $categories = Category::query()->whereIn('id', $ids)->get()->keyBy('id');

        return $ids->map(fn ($id) => $categories->get($id))->filter()->values();
    }
}
