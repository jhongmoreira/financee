<?php

namespace App\Livewire\Cards;

use App\Models\Account;
use App\Models\CreditCard;
use App\Models\InvoicePayment;
use App\Models\Transaction;
use App\Services\InvoiceService;
use App\Support\Month;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

class Invoice extends Component
{
    #[Locked]
    public int $cardId;

    #[Url(as: 'mes', except: '')]
    public string $month = '';

    // Pagamento
    public int $pay_amount = 0;

    public ?string $pay_account_id = null;

    public string $pay_date = '';

    public bool $carry_remainder = true;

    public function mount(CreditCard $card, InvoiceService $invoices): void
    {
        $this->cardId = $card->id;

        if (! preg_match('/^\d{4}-\d{2}$/', $this->month)) {
            $this->month = $invoices->currentInvoice($card)->reference_month;
        }
    }

    public function previousMonth(): void
    {
        $this->month = Month::key(Month::parse($this->month)->subMonth());
    }

    public function nextMonth(): void
    {
        $this->month = Month::key(Month::parse($this->month)->addMonth());
    }

    #[On('entry-saved')]
    public function refresh(): void {}

    public function startPayment(InvoiceService $invoices): void
    {
        $card = $this->card();
        $summary = $invoices->summary($invoices->byReference($card, $this->month));

        $this->resetValidation();
        $this->pay_amount = max(0, $summary['remaining']);
        $this->pay_account_id = (string) ($card->payment_account_id ?? Account::query()->active()->ordered()->value('id') ?? '') ?: null;
        $this->pay_date = today()->toDateString();
        // Em fatura ainda aberta, pagar menos é adiantamento: o restante fica nela.
        $this->carry_remainder = ! in_array($summary['status'], ['open', 'future'], true);

        $this->dispatch('open-sheet', 'pay-invoice');
    }

    public function pay(InvoiceService $invoices): void
    {
        $this->validate([
            'pay_amount' => ['required', 'integer', 'min:1', 'max:100000000000'],
            'pay_account_id' => ['required', 'integer', Rule::exists('accounts', 'id')->where('user_id', Auth::id())],
            'pay_date' => ['required', 'date_format:Y-m-d'],
        ], attributes: ['pay_amount' => 'valor', 'pay_account_id' => 'conta', 'pay_date' => 'data']);

        $invoice = $invoices->byReference($this->card(), $this->month);
        $invoices->pay($invoice, Account::findOrFail($this->pay_account_id), $this->pay_amount, CarbonImmutable::parse($this->pay_date), $this->carry_remainder);

        $this->dispatch('close-sheet', 'pay-invoice');
        $this->dispatch('entry-saved');
        $this->dispatch('toast', message: 'Pagamento registrado.');
    }

    public function undoPayment(int $paymentId, InvoiceService $invoices): void
    {
        $invoices->undoPayment(InvoicePayment::findOrFail($paymentId));
        $this->dispatch('entry-saved');
        $this->dispatch('toast', message: 'Pagamento desfeito.');
    }

    public function render(InvoiceService $invoices): View
    {
        $card = $this->card();
        $invoice = $invoices->byReference($card, $this->month);
        $summary = $invoices->summary($invoice);

        $rows = Transaction::query()->where('invoice_id', $invoice->id)->with('category')
            ->orderByDesc('date')->orderByDesc('id')->get()
            ->map(fn (Transaction $t) => [
                'kind' => 'transaction',
                'id' => $t->id,
                'date' => $t->date,
                'day' => $t->date->toDateString(),
                'description' => $t->description.($t->installmentLabel() ? ' ('.$t->installmentLabel().')' : ''),
                'subtitle' => $t->category->name ?? ($t->type === 'income' ? 'Estorno / crédito' : 'Sem categoria'),
                'amount' => $t->signedAmount(),
                'paid' => true,
                'overdue' => false,
                'icon' => $t->category->icon ?? ($t->type === 'income' ? 'arrow-uturn-left' : 'credit-card'),
                'color' => $t->category->color ?? '#7c3aed',
                'type' => $t->type,
            ]);

        return view('livewire.cards.invoice', [
            'card' => $card,
            'invoice' => $invoice,
            'summary' => $summary,
            'groups' => $rows->groupBy('day'),
            'payments' => $invoice->payments()->with('account')->get(),
            'accounts' => Account::query()->active()->ordered()->pluck('name', 'id')->all(),
            'used' => $card->usedLimit(),
        ])->title($card->name.' · Fatura');
    }

    private function card(): CreditCard
    {
        return CreditCard::findOrFail($this->cardId);
    }
}
