<?php

use App\Models\Account;
use App\Models\Transaction;
use App\Models\Transfer;
use App\Models\User;
use App\Services\AccountBalanceService;
use App\Services\MonthSummary;
use Carbon\CarbonImmutable;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-11-15 10:00');
    $this->actingAs(User::factory()->create());
    $this->a = Account::create(['name' => 'A', 'type' => 'checking', 'color' => '#059669', 'initial_balance' => 100000]);
    $this->b = Account::create(['name' => 'B', 'type' => 'savings', 'color' => '#059669', 'initial_balance' => 0]);
});

afterEach(fn () => CarbonImmutable::setTestNow());

it('calcula saldo atual só com lançamentos efetivados', function () {
    Transaction::factory()->create(['account_id' => $this->a->id, 'amount' => 30000, 'date' => '2026-11-10', 'paid_at' => '2026-11-10']);
    Transaction::factory()->income()->create(['account_id' => $this->a->id, 'amount' => 5000, 'date' => '2026-11-12', 'paid_at' => '2026-11-12']);
    Transaction::factory()->pending()->create(['account_id' => $this->a->id, 'amount' => 99999, 'date' => '2026-11-20']);

    expect($this->a->balance())->toBe(100000 - 30000 + 5000);
});

it('transferências movem saldo entre contas', function () {
    Transfer::create(['from_account_id' => $this->a->id, 'to_account_id' => $this->b->id, 'amount' => 25000, 'date' => '2026-11-14', 'paid_at' => '2026-11-14']);
    Transfer::create(['from_account_id' => $this->a->id, 'to_account_id' => $this->b->id, 'amount' => 1000, 'date' => '2026-11-25']); // agendada

    $balances = app(AccountBalanceService::class)->current();
    expect($balances[$this->a->id])->toBe(75000)->and($balances[$this->b->id])->toBe(25000);

    $projected = app(AccountBalanceService::class)->projected(CarbonImmutable::parse('2026-11-30'));
    expect($projected[$this->a->id])->toBe(74000)->and($projected[$this->b->id])->toBe(26000);
});

it('saldo previsto inclui pendentes até a data', function () {
    Transaction::factory()->pending()->create(['account_id' => $this->a->id, 'amount' => 20000, 'date' => '2026-11-28']);
    Transaction::factory()->pending()->create(['account_id' => $this->a->id, 'amount' => 50000, 'date' => '2026-12-05']);

    $summary = app(MonthSummary::class);
    $month = CarbonImmutable::parse('2026-11-01');

    expect($summary->currentTotal())->toBe(100000)
        ->and($summary->projectedTotal($month))->toBe(80000)
        ->and($summary->projectedTotal($month->addMonth()))->toBe(30000);
});

it('o cache de saldo é limpo ao salvar um lançamento', function () {
    expect($this->a->balance())->toBe(100000);
    Transaction::factory()->create(['account_id' => $this->a->id, 'amount' => 1000, 'date' => '2026-11-15', 'paid_at' => '2026-11-15']);
    expect($this->a->balance())->toBe(99000);
});

it('resumo do mês ignora transferências e ajustes', function () {
    Transaction::factory()->create(['account_id' => $this->a->id, 'amount' => 3000, 'date' => '2026-11-02', 'paid_at' => '2026-11-02']);
    Transaction::factory()->pending()->create(['account_id' => $this->a->id, 'amount' => 2000, 'date' => '2026-11-29']);
    Transaction::factory()->income()->create(['account_id' => $this->a->id, 'amount' => 10000, 'date' => '2026-11-05', 'paid_at' => '2026-11-05']);
    Transaction::factory()->income()->create(['account_id' => $this->a->id, 'amount' => 777, 'date' => '2026-11-05', 'paid_at' => '2026-11-05', 'is_adjustment' => true]);
    Transaction::factory()->create(['account_id' => $this->a->id, 'amount' => 4000, 'date' => '2026-10-31', 'paid_at' => '2026-10-31']);
    Transfer::create(['from_account_id' => $this->a->id, 'to_account_id' => $this->b->id, 'amount' => 5000, 'date' => '2026-11-03', 'paid_at' => '2026-11-03']);

    expect(app(MonthSummary::class)->totals(CarbonImmutable::parse('2026-11-01')))->toBe([
        'income' => 10000, 'expense' => 5000, 'income_paid' => 10000, 'expense_paid' => 3000, 'balance' => 5000,
    ]);
});

it('contas fora do total e arquivadas não entram no saldo geral', function () {
    $this->b->update(['initial_balance' => 5000, 'include_in_total' => false]);
    Account::create(['name' => 'Velha', 'type' => 'checking', 'color' => '#059669', 'initial_balance' => 9000, 'archived_at' => now()]);

    expect(app(MonthSummary::class)->currentTotal())->toBe(100000);
});
