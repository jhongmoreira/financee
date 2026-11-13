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
use Illuminate\Support\Facades\Auth;
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

it('gera até 31/12 do ano seguinte sem escorregar o dia', function () {
    $r = recurrence(['start_date' => '2026-01-31']);
    $this->service->generate($r);

    $dates = Transaction::where('recurrence_id', $r->id)->orderBy('date')->pluck('date')->map->toDateString();
    expect($dates->first())->toBe('2026-01-31')
        ->and($dates->get(1))->toBe('2026-02-28')
        ->and($dates->get(2))->toBe('2026-03-31') // não fica preso no dia 28
        ->and($dates->last())->toBe('2027-12-31')
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
        ->and($byDate->count())->toBe(16); // set/26 .. dez/27
});

it('encerrar remove só os pendentes futuros', function () {
    $r = recurrence(['start_date' => '2026-10-10']);
    $this->service->generate($r);
    Transaction::where('recurrence_id', $r->id)->where('date', '2026-10-10')->update(['paid_at' => '2026-10-10']);

    $this->service->stop($r, CarbonImmutable::today());

    expect(Transaction::where('recurrence_id', $r->id)->pluck('date')->map->toDateString()->all())->toBe(['2026-10-10', '2026-11-10'])
        ->and($r->fresh()->end_date->toDateString())->toBe('2026-11-14');
});

it('assinatura no cartão gera até o fim do ano seguinte e só ocupa limite a partir da cobrança', function () {
    $card = CreditCard::create(['name' => 'C', 'brand' => 'visa', 'limit' => 100000, 'closing_day' => 3, 'due_day' => 10, 'color' => '#7c3aed']);
    $r = recurrence(['credit_card_id' => $card->id, 'account_id' => null, 'amount' => 3990, 'start_date' => '2026-11-05']);
    $this->service->generate($r);

    expect(Transaction::where('recurrence_id', $r->id)->count())->toBe(14) // nov/26 .. dez/27
        ->and(Transaction::where('recurrence_id', $r->id)->whereNull('invoice_id')->count())->toBe(0)
        ->and($card->usedLimit())->toBe(3990); // só a de 05/11 (já cobrada)
});

it('fixo no cartão começando no futuro aparece na fatura certa', function () {
    // Caso relatado: em novembro, fixo a partir de 10/02 num cartão que fecha dia 8 e vence dia 15
    $card = CreditCard::create(['name' => 'LuizaCred', 'brand' => 'visa', 'limit' => 500000, 'closing_day' => 8, 'due_day' => 15, 'color' => '#2563eb']);
    $r = recurrence(['credit_card_id' => $card->id, 'account_id' => null, 'amount' => 5990, 'start_date' => '2027-02-10']);
    $this->service->generate($r);

    $first = Transaction::where('recurrence_id', $r->id)->orderBy('date')->with('invoice')->first();
    expect($first->date->toDateString())->toBe('2027-02-10')
        ->and($first->invoice->reference_month)->toBe('2027-03') // 10/02 é depois do fechamento (08/02): fatura de março
        ->and($card->usedLimit())->toBe(0);

    // e entra na previsão do mês da fatura
    $months = collect(app(ForecastService::class)->months(6))->keyBy(fn ($m) => $m['month']->format('Y-m'));
    expect($months['2027-03']['invoices'])->toBe(5990);
});

it('cria lançamento fixo pelo formulário', function () {
    Livewire::test(EntryForm::class)->call('open', 'expense')
        ->set('amount', 150000)->set('description', 'Aluguel')->set('category_id', (string) $this->rent->id)
        ->set('account_id', (string) $this->account->id)->set('repeat', 'fixed')->set('frequency', 'monthly')
        ->call('save')->assertHasNoErrors();

    $r = Recurrence::firstOrFail();
    expect(Transaction::where('recurrence_id', $r->id)->count())->toBe(14) // nov/26 .. dez/27
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

it('fixo quinzenal repete a cada 2 semanas, sempre no mesmo dia da semana', function () {
    $r = recurrence(['frequency' => 'biweekly', 'start_date' => '2026-11-17', 'end_date' => '2027-01-31', 'amount' => 60000]); // terça
    $this->service->generate($r);

    $dates = Transaction::where('recurrence_id', $r->id)->orderBy('date')->pluck('date');

    expect($dates->map->toDateString()->all())
        ->toBe(['2026-11-17', '2026-12-01', '2026-12-15', '2026-12-29', '2027-01-12', '2027-01-26'])
        ->and($dates->map->dayOfWeekIso->unique()->all())->toBe([2]) // todas numa terça
        ->and($r->frequencyLabel())->toBe('Quinzenal')
        ->and($r->monthlyAmount())->toBe(130357); // 600 × 365/14/12 ≈ 1.303,57 por mês
});

it('cria fixo quinzenal pelo formulário', function () {
    Livewire::test(EntryForm::class)->call('open', 'income')
        ->set('amount', 250000)->set('description', 'Pagamento quinzenal')->set('account_id', (string) $this->account->id)
        ->set('repeat', 'fixed')->set('frequency', 'biweekly')->set('end_date', '2026-12-31')
        ->call('save')->assertHasNoErrors();

    expect(Recurrence::firstOrFail()->frequency)->toBe('biweekly')
        ->and(Transaction::orderBy('date')->pluck('date')->map->toDateString()->all())
        ->toBe(['2026-11-15', '2026-11-29', '2026-12-13', '2026-12-27']); // domingos
});

it('horizonte: restante do ano atual + ano seguinte inteiro, estendido pela rotina diária', function () {
    $r = recurrence(['start_date' => '2026-11-10']);
    $this->service->generate($r);
    expect(Transaction::where('recurrence_id', $r->id)->max('date'))->toBe('2027-12-10');

    // Na virada do ano a rotina diária estende até dez do ano seguinte
    CarbonImmutable::setTestNow('2027-01-02 03:10');
    Auth::forgetUser();
    $this->service->runDaily();

    expect(Transaction::withoutGlobalScopes()->where('recurrence_id', $r->id)->max('date'))->toBe('2028-12-10')
        ->and(Transaction::withoutGlobalScopes()->where('recurrence_id', $r->id)->count())->toBe(26); // nov/26 .. dez/28
});
