<?php

use App\Livewire\EntryForm;
use App\Livewire\Planning;
use App\Livewire\Recurrences\Index as RecurrencesIndex;
use App\Models\Account;
use App\Models\Category;
use App\Models\CreditCard;
use App\Models\Recurrence;
use App\Models\Transaction;
use App\Models\User;
use App\Services\ForecastService;
use App\Services\InvoiceService;
use App\Services\RecurrenceService;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-11-15 10:00');
    $this->actingAs(User::factory()->create());
    $this->account = Account::create(['name' => 'Conta', 'type' => 'checking', 'color' => '#059669', 'initial_balance' => 100000]);
    $this->rent = Category::create(['type' => 'expense', 'name' => 'Aluguel', 'icon' => 'home', 'color' => '#2563eb']);
    $this->service = app(RecurrenceService::class);
});

afterEach(fn () => CarbonImmutable::setTestNow());

function recurrence(array $attrs = []): Recurrence
{
    return Recurrence::create($attrs + [
        'type' => 'expense', 'description' => 'Aluguel', 'amount' => 150000, 'account_id' => test()->account->id,
        'frequency' => 'monthly', 'start_date' => '2026-11-10',
    ]);
}

it('gera 12 meses à frente sem escorregar o dia', function () {
    $r = recurrence(['start_date' => '2026-01-31']);
    $this->service->generate($r);

    $dates = Transaction::where('recurrence_id', $r->id)->orderBy('date')->pluck('date')->map->toDateString();
    expect($dates->first())->toBe('2026-01-31')
        ->and($dates->get(1))->toBe('2026-02-28')
        ->and($dates->get(2))->toBe('2026-03-31') // não fica preso no dia 28
        ->and($dates->last())->toBe('2027-11-30')
        ->and(Transaction::whereNotNull('paid_at')->count())->toBe(0);
});

it('respeita frequência e data final, e é idempotente', function () {
    $r = recurrence(['frequency' => 'quarterly', 'end_date' => '2027-06-30']);
    $this->service->generate($r);
    $this->service->generate($r);

    expect(Transaction::where('recurrence_id', $r->id)->orderBy('date')->pluck('date')->map->toDateString()->all())
        ->toBe(['2026-11-10', '2027-02-10', '2027-05-10']);
});

it('ano bissexto: anual em 29/02 cai em 28/02', function () {
    $r = recurrence(['frequency' => 'yearly', 'start_date' => '2024-02-29']);
    $this->service->generate($r);

    expect(Transaction::where('recurrence_id', $r->id)->orderBy('date')->pluck('date')->map->toDateString()->all())
        ->toBe(['2024-02-29', '2025-02-28', '2026-02-28', '2027-02-28']);
});

it('débito automático efetiva no dia pela rotina diária', function () {
    $r = recurrence(['auto_mark_paid' => true, 'start_date' => '2026-11-20']);
    $this->service->generate($r);
    expect(Transaction::whereNotNull('paid_at')->count())->toBe(0);

    CarbonImmutable::setTestNow('2026-11-21 04:00');
    $this->service->runDaily();

    expect(Transaction::whereNotNull('paid_at')->pluck('date')->map->toDateString()->all())->toBe(['2026-11-20']);
});

it('alterar deste em diante não mexe nos já pagos', function () {
    $r = recurrence(['start_date' => '2026-09-10']);
    $this->service->generate($r);
    Transaction::where('recurrence_id', $r->id)->where('date', '<', '2026-11-01')->update(['paid_at' => '2026-10-10']);

    $this->service->updateFrom($r, CarbonImmutable::parse('2026-11-15'), ['amount' => 170000]);

    $byDate = Transaction::where('recurrence_id', $r->id)->orderBy('date')->get()->mapWithKeys(fn ($t) => [$t->date->toDateString() => $t->amount]);
    expect($byDate['2026-09-10'])->toBe(150000)
        ->and($byDate['2026-11-10'])->toBe(150000) // antes de 15/11, pendente, mantém
        ->and($byDate['2026-12-10'])->toBe(170000)
        ->and($byDate->count())->toBe(15);
});

it('encerrar remove só os pendentes futuros', function () {
    $r = recurrence(['start_date' => '2026-10-10']);
    $this->service->generate($r);
    Transaction::where('recurrence_id', $r->id)->where('date', '2026-10-10')->update(['paid_at' => '2026-10-10']);

    $this->service->stop($r, CarbonImmutable::today());

    expect(Transaction::where('recurrence_id', $r->id)->pluck('date')->map->toDateString()->all())->toBe(['2026-10-10', '2026-11-10'])
        ->and($r->fresh()->end_date->toDateString())->toBe('2026-11-14');
});

it('assinatura no cartão só gera até o mês seguinte', function () {
    $card = CreditCard::create(['name' => 'C', 'brand' => 'visa', 'limit' => 100000, 'closing_day' => 3, 'due_day' => 10, 'color' => '#7c3aed']);
    $r = recurrence(['credit_card_id' => $card->id, 'account_id' => null, 'amount' => 3990, 'start_date' => '2026-11-05']);
    $this->service->generate($r);

    expect(Transaction::where('recurrence_id', $r->id)->count())->toBe(2) // 05/11 e 05/12
        ->and(Transaction::whereNotNull('invoice_id')->count())->toBe(2);
});

it('cria lançamento fixo pelo formulário', function () {
    Livewire::test(EntryForm::class)->call('open', 'expense')
        ->set('amount', 150000)->set('description', 'Aluguel')->set('category_id', (string) $this->rent->id)
        ->set('account_id', (string) $this->account->id)->set('repeat', 'fixed')->set('frequency', 'monthly')
        ->call('save')->assertHasNoErrors();

    $r = Recurrence::firstOrFail();
    expect(Transaction::where('recurrence_id', $r->id)->count())->toBe(13) // nov/26 .. nov/27
        ->and(Transaction::where('recurrence_id', $r->id)->whereNotNull('paid_at')->pluck('date')->map->toDateString()->all())->toBe(['2026-11-15']);
});

it('parcela em conta (boleto) pelo formulário', function () {
    Livewire::test(EntryForm::class)->call('open', 'expense')
        ->set('date', '2026-11-20')
        ->set('amount', 100000)->set('description', 'Carnê loja')->set('account_id', (string) $this->account->id)
        ->set('repeat', 'installments')->assertSet('installments', 2)->set('installments', 3)
        ->call('save')->assertHasNoErrors();

    expect(Transaction::orderBy('date')->get()->map(fn ($t) => [$t->date->toDateString(), $t->amount, $t->installmentLabel(), $t->paid_at])->all())
        ->toBe([['2026-11-20', 33334, '1/3', null], ['2026-12-20', 33333, '2/3', null], ['2027-01-20', 33333, '3/3', null]]);
});

it('edita fixo "este e os próximos" pelo formulário', function () {
    $r = recurrence();
    $this->service->generate($r);
    $dec = Transaction::where('recurrence_id', $r->id)->whereDate('date', '2026-12-10')->first();

    Livewire::test(EntryForm::class)->call('edit', 'transaction', $dec->id)
        ->assertSet('recurrenceId', $r->id)->set('scope', 'future')->set('amount', 160000)->call('save')->assertHasNoErrors();

    expect(Transaction::where('recurrence_id', $r->id)->orderBy('date')->pluck('amount')->take(3)->all())->toBe([150000, 160000, 160000]);

    Livewire::test(EntryForm::class)->call('edit', 'transaction', $dec->id)->set('scope', 'future')->call('delete');
    expect(Transaction::where('recurrence_id', $r->id)->count())->toBe(1);
});

it('lista fixos com total mensal e encerra pela tela', function () {
    $r = recurrence();
    $this->service->generate($r);
    recurrence(['description' => 'Salário', 'type' => 'income', 'amount' => 500000]);

    Livewire::test(RecurrencesIndex::class)
        ->assertSee('Aluguel')->assertSee('R$ 1.500,00')->assertSee('R$ 5.000,00')
        ->call('edit', $r->id)->call('stop');

    expect(Transaction::where('recurrence_id', $r->id)->where('date', '>=', '2026-11-15')->count())->toBe(0);
});

it('prevê o saldo mês a mês com fixos e faturas', function () {
    $this->service->generate(recurrence(['amount' => 30000])); // aluguel 10/11 (pendente), 10/12...
    $this->service->generate(recurrence(['type' => 'income', 'description' => 'Salário', 'amount' => 50000, 'start_date' => '2026-12-05']));
    $card = CreditCard::create(['name' => 'C', 'brand' => 'visa', 'limit' => 100000, 'closing_day' => 3, 'due_day' => 10, 'color' => '#7c3aed']);
    app(InvoiceService::class)->createPurchase($card, 'X', null, CarbonImmutable::today(), 10000); // fatura vence 10/12

    $months = app(ForecastService::class)->months(3);

    expect($months[0])->toMatchArray(['start' => 100000, 'income' => 0, 'expense' => 30000, 'invoices' => 0, 'end' => 70000])
        ->and($months[1])->toMatchArray(['income' => 50000, 'expense' => 30000, 'invoices' => 10000, 'end' => 80000])
        ->and($months[2]['end'])->toBe(100000);

    Livewire::test(Planning::class)->set('tab', 'previsao')->assertSee('Saldo geral ao fim de cada mês')->assertSee('R$ 700,00');
});
