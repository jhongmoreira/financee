<?php

use App\Livewire\Cards\Index as CardsIndex;
use App\Livewire\Cards\Invoice as InvoicePage;
use App\Livewire\EntryForm;
use App\Livewire\Transactions\Index as Statement;
use App\Models\Account;
use App\Models\Category;
use App\Models\CreditCard;
use App\Models\InstallmentPlan;
use App\Models\InvoicePayment;
use App\Models\Transaction;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\MonthSummary;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-11-15 10:00');
    $this->actingAs(User::factory()->create());
    $this->account = Account::create(['name' => 'Conta', 'type' => 'checking', 'color' => '#059669', 'initial_balance' => 500000]);
    $this->card = CreditCard::create(['name' => 'Roxinho', 'brand' => 'mastercard', 'limit' => 300000, 'closing_day' => 3, 'due_day' => 10, 'color' => '#820ad1', 'payment_account_id' => $this->account->id]);
    $this->food = Category::create(['type' => 'expense', 'name' => 'Alimentação', 'icon' => 'shopping-cart', 'color' => '#ea580c']);
});

afterEach(fn () => CarbonImmutable::setTestNow());

it('lança compra à vista no cartão na fatura certa', function () {
    Livewire::test(EntryForm::class)->call('open', 'card')
        ->assertSet('credit_card_id', (string) $this->card->id)
        ->assertSet('invoice_ref', '2026-12')
        ->set('amount', 8990)->set('description', 'iFood')->set('category_id', (string) $this->food->id)
        ->call('save')->assertHasNoErrors();

    $t = Transaction::firstOrFail();
    expect($t)->account_id->toBeNull()->credit_card_id->toBe($this->card->id)->amount->toBe(8990)
        ->and($t->invoice->reference_month)->toBe('2026-12')
        ->and($this->account->balance())->toBe(500000); // compra no cartão não mexe na conta
});

it('parcela pelo valor da parcela', function () {
    Livewire::test(EntryForm::class)->call('open', 'card')
        ->set('amount', 15000)->set('installments', 4)->set('installment_mode', 'parcel')->set('description', 'Curso')
        ->call('save')->assertHasNoErrors();

    expect(InstallmentPlan::first()->total_amount)->toBe(60000)
        ->and(Transaction::orderBy('installment_number')->pluck('amount')->all())->toBe([15000, 15000, 15000, 15000]);
});

it('edita parcelas por escopo e exclui as próximas', function () {
    app(InvoiceService::class)->createPurchase($this->card, 'Geladeira', null, CarbonImmutable::today(), 400000, 4);
    $third = Transaction::where('installment_number', 3)->first();

    Livewire::test(EntryForm::class)->call('edit', 'transaction', $third->id)
        ->assertSet('kind', 'card')->assertSet('planId', $third->installment_plan_id)
        ->set('scope', 'future')->set('description', 'Geladeira nova')->call('save')->assertHasNoErrors();

    expect(Transaction::orderBy('installment_number')->pluck('description')->all())
        ->toBe(['Geladeira', 'Geladeira', 'Geladeira nova', 'Geladeira nova']);

    Livewire::test(EntryForm::class)->call('edit', 'transaction', $third->id)->set('scope', 'future')->call('delete');
    expect(Transaction::count())->toBe(2);

    $first = Transaction::where('installment_number', 1)->first();
    Livewire::test(EntryForm::class)->call('edit', 'transaction', $first->id)->set('scope', 'all')->call('delete');
    expect(Transaction::count())->toBe(0)->and(InstallmentPlan::count())->toBe(0);
});

it('lança estorno que reduz a fatura', function () {
    Livewire::test(EntryForm::class)->call('open', 'card')
        ->set('is_refund', true)->set('amount', 1000)->set('description', 'Estorno loja')
        ->call('save')->assertHasNoErrors();

    expect(Transaction::first()->type)->toBe('income');
});

it('paga a fatura pela tela e desfaz', function () {
    $service = app(InvoiceService::class);
    $service->createPurchase($this->card, 'Mercado', null, CarbonImmutable::parse('2026-10-20'), 20000); // fatura 2026-11 vencida

    $page = Livewire::test(InvoicePage::class, ['card' => $this->card])
        ->set('month', '2026-11')
        ->assertSee('Mercado')->assertSee('Vencida')
        ->call('startPayment')
        ->assertSet('pay_amount', 20000)->assertSet('pay_account_id', (string) $this->account->id)
        ->call('pay')->assertHasNoErrors()->assertSee('Paga');

    expect($this->account->fresh()->balance())->toBe(480000);

    $payment = InvoicePayment::first();
    $page->call('undoPayment', $payment->id);
    expect($this->account->fresh()->balance())->toBe(500000);
});

it('extrato mostra compra no cartão e pagamento de fatura', function () {
    $service = app(InvoiceService::class);
    $service->createPurchase($this->card, 'Farmácia', null, CarbonImmutable::parse('2026-11-05'), 3000);
    $service->pay($service->byReference($this->card, '2026-11'), $this->account, 1000, CarbonImmutable::parse('2026-11-08'));

    Livewire::test(Statement::class)
        ->assertSee('Farmácia')->assertSee('Cartão Roxinho')
        ->assertSee('Pagamento fatura Roxinho');
});

it('lista cartões com fatura atual e não exclui cartão com compras', function () {
    app(InvoiceService::class)->createPurchase($this->card, 'X', null, CarbonImmutable::today(), 5000);

    Livewire::test(CardsIndex::class)->assertSee('Fatura Dez/26')->assertSee('R$ 50,00')
        ->call('edit', $this->card->id)->call('delete')->assertDispatched('toast');

    expect(CreditCard::find($this->card->id))->not->toBeNull();
});

it('saldo previsto do mês desconta fatura a vencer', function () {
    app(InvoiceService::class)->createPurchase($this->card, 'X', null, CarbonImmutable::parse('2026-10-25'), 30000); // vence 10/11 (vencida, ainda conta)

    expect(app(MonthSummary::class)->projectedTotal(CarbonImmutable::parse('2026-11-01')))->toBe(470000);
});

it('exibe a Início com cartões', function () {
    $this->get('/')->assertOk()->assertSee('Roxinho')->assertSee('Fatura Dez/26');
});

it('em fatura aberta, pagar menos é adiantamento (não leva o restante)', function () {
    app(InvoiceService::class)->createPurchase($this->card, 'X', null, CarbonImmutable::today(), 10000); // fatura 2026-12 aberta

    Livewire::test(InvoicePage::class, ['card' => $this->card])
        ->assertSet('month', '2026-12')
        ->call('startPayment')->assertSet('carry_remainder', false)
        ->set('pay_amount', 4000)->call('pay');

    $summary = app(InvoiceService::class)->summary(app(InvoiceService::class)->byReference($this->card, '2026-12'));
    expect($summary)->toMatchArray(['remaining' => 6000, 'carried_out' => 0]);
});
