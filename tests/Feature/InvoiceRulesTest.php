<?php

use App\Models\Account;
use App\Models\CreditCard;
use App\Models\InstallmentPlan;
use App\Models\Transaction;
use App\Models\User;
use App\Services\InvoiceService;
use Carbon\CarbonImmutable;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-11-15 10:00');
    $this->actingAs(User::factory()->create());
    $this->service = app(InvoiceService::class);
    $this->account = Account::create(['name' => 'Conta', 'type' => 'checking', 'color' => '#059669', 'initial_balance' => 1000000]);
});

afterEach(fn () => CarbonImmutable::setTestNow());

function card(int $closing, int $due, int $limit = 500000): CreditCard
{
    return CreditCard::create(['name' => 'Cartão', 'brand' => 'visa', 'limit' => $limit, 'closing_day' => $closing, 'due_day' => $due, 'color' => '#7c3aed']);
}

it('define a fatura pela data da compra', function (int $closing, int $due, string $purchase, string $ref, string $closingDate, string $dueDate) {
    $invoice = $this->service->invoiceFor(card($closing, $due), CarbonImmutable::parse($purchase));

    expect($invoice->reference_month)->toBe($ref)
        ->and($invoice->closing_date->toDateString())->toBe($closingDate)
        ->and($invoice->due_date->toDateString())->toBe($dueDate);
})->with([
    'antes do fechamento' => [3, 10, '2026-11-02', '2026-11', '2026-11-03', '2026-11-10'],
    'no dia do fechamento vai p/ próxima' => [3, 10, '2026-11-03', '2026-12', '2026-12-03', '2026-12-10'],
    'vencimento no mês seguinte' => [28, 5, '2026-11-27', '2026-12', '2026-11-28', '2026-12-05'],
    'depois do fechamento, vence em 2 meses' => [28, 5, '2026-11-29', '2027-01', '2026-12-28', '2027-01-05'],
    'fechamento 31 em fevereiro' => [31, 8, '2027-02-27', '2027-03', '2027-02-28', '2027-03-08'],
    'fechamento 31: dia 28/02 já é fechamento' => [31, 8, '2027-02-28', '2027-04', '2027-03-31', '2027-04-08'],
    'virada de ano' => [20, 27, '2026-12-25', '2027-01', '2027-01-20', '2027-01-27'],
    'vencimento 31 em mês de 30' => [20, 31, '2026-11-05', '2026-11', '2026-11-20', '2026-11-30'],
]);

it('não duplica faturas', function () {
    $card = card(3, 10);
    $a = $this->service->invoiceFor($card, CarbonImmutable::parse('2026-11-01'));
    $b = $this->service->invoiceFor($card, CarbonImmutable::parse('2026-10-20'));
    $c = $this->service->byReference($card, '2026-11');

    expect($a->id)->toBe($b->id)->toBe($c->id);
});

it('parcela em faturas consecutivas com o resto na 1ª parcela', function () {
    $card = card(3, 10);
    $items = $this->service->createPurchase($card, 'TV', null, CarbonImmutable::parse('2026-11-15'), 100000, 3);

    expect($items->pluck('amount')->all())->toBe([33334, 33333, 33333])
        ->and($items->map(fn ($t) => $t->invoice->reference_month)->all())->toBe(['2026-12', '2027-01', '2027-02'])
        ->and($items->map(fn ($t) => $t->installmentLabel())->all())->toBe(['1/3', '2/3', '3/3'])
        ->and(InstallmentPlan::first()->total_amount)->toBe(100000);
});

it('usa o limite com parcelas futuras e libera com pagamento', function () {
    $card = card(3, 10, 500000);
    $this->service->createPurchase($card, 'TV', null, CarbonImmutable::parse('2026-11-15'), 300000, 3);
    $this->service->createPurchase($card, 'Estorno', null, CarbonImmutable::parse('2026-11-15'), 10000, isRefund: true);

    expect($card->usedLimit())->toBe(290000)->and($card->availableLimit())->toBe(210000);

    $this->service->pay($this->service->byReference($card, '2026-12'), $this->account, 90000, CarbonImmutable::today());
    expect($card->fresh()->usedLimit())->toBe(200000);
});

it('calcula totais e situação da fatura', function () {
    $card = card(3, 10);
    $this->service->createPurchase($card, 'A', null, CarbonImmutable::parse('2026-10-20'), 20000); // fatura 2026-11 (fechou 03/11, vence 10/11)
    $this->service->createPurchase($card, 'B', null, CarbonImmutable::parse('2026-11-10'), 5000);  // fatura 2026-12 (aberta)
    $this->service->createPurchase($card, 'C', null, CarbonImmutable::parse('2026-12-10'), 1000);  // fatura 2027-01 (futura)

    $nov = $this->service->summary($this->service->byReference($card, '2026-11'));
    $dez = $this->service->summary($this->service->byReference($card, '2026-12'));
    $jan = $this->service->summary($this->service->byReference($card, '2027-01'));

    expect($nov)->toMatchArray(['total' => 20000, 'remaining' => 20000, 'status' => 'overdue'])
        ->and($dez)->toMatchArray(['total' => 5000, 'status' => 'open'])
        ->and($jan['status'])->toBe('future');
});

it('pagamento parcial pode levar o restante para a próxima fatura e pode ser desfeito', function () {
    $card = card(3, 10);
    $this->service->createPurchase($card, 'A', null, CarbonImmutable::parse('2026-10-20'), 20000);
    $nov = $this->service->byReference($card, '2026-11');

    $payment = $this->service->pay($nov, $this->account, 15000, CarbonImmutable::today(), carryRemainder: true);

    $dez = $this->service->byReference($card, '2026-12');
    expect($this->service->summary($nov->fresh()))->toMatchArray(['paid' => 15000, 'remaining' => 0, 'status' => 'paid'])
        ->and($this->service->summary($dez->fresh()))->toMatchArray(['carried_in' => 5000, 'total' => 5000])
        ->and($this->account->balance())->toBe(1000000 - 15000);

    $this->service->undoPayment($payment);
    expect($this->service->summary($nov->fresh()))->toMatchArray(['remaining' => 20000, 'carried_out' => 0])
        ->and($this->service->summary($dez->fresh())['carried_in'])->toBe(0)
        ->and($this->account->fresh()->balance())->toBe(1000000);
});

it('pagamento parcial sem levar o restante fica como parcial', function () {
    $card = card(20, 27);
    $this->service->createPurchase($card, 'A', null, CarbonImmutable::parse('2026-10-25'), 20000); // fatura 2026-11: fechou 20/11? não: compra 25/10 >= 20/10 → fecha 20/11
    $inv = $this->service->byReference($card, '2026-11');
    CarbonImmutable::setTestNow('2026-11-22');

    $this->service->pay($inv, $this->account, 5000, CarbonImmutable::today());
    expect($this->service->summary($inv->fresh()))->toMatchArray(['remaining' => 15000, 'status' => 'partial']);
});

it('pagamento a maior vira crédito na próxima fatura', function () {
    $card = card(3, 10);
    $this->service->createPurchase($card, 'A', null, CarbonImmutable::parse('2026-10-20'), 20000);
    $nov = $this->service->byReference($card, '2026-11');

    $this->service->pay($nov, $this->account, 25000, CarbonImmutable::today());

    expect($this->service->summary($this->service->byReference($card, '2026-12'))['carried_in'])->toBe(-5000)
        ->and($this->service->summary($nov->fresh())['remaining'])->toBe(0);
});

it('antecipa parcelas futuras para a fatura atual', function () {
    $card = card(3, 10);
    $this->service->createPurchase($card, 'Celular', null, CarbonImmutable::parse('2026-09-20'), 60000, 6); // começa em 2026-10

    $moved = $this->service->anticipate(InstallmentPlan::firstOrFail());

    expect($moved)->toBe(3) // 2027-01..03 → 2026-12 (atual)
        ->and(Transaction::whereHas('invoice', fn ($q) => $q->where('reference_month', '2026-12'))->count())->toBe(4);
});
