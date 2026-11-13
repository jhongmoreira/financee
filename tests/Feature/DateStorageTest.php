<?php

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use App\Services\MonthSummary;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-11-30 18:00');
    $this->actingAs(User::factory()->create());
    $this->account = Account::create(['name' => 'Conta', 'type' => 'checking', 'color' => '#059669', 'initial_balance' => 10000]);
});

afterEach(fn () => CarbonImmutable::setTestNow());

it('grava datas só com AAAA-MM-DD', function () {
    $t = Transaction::factory()->create(['account_id' => $this->account->id, 'date' => CarbonImmutable::now(), 'paid_at' => '2026-11-30 15:00:00']);

    expect(DB::table('transactions')->where('id', $t->id)->first())
        ->date->toBe('2026-11-30')->paid_at->toBe('2026-11-30')
        ->and($t->fresh()->date->toDateString())->toBe('2026-11-30');
});

it('lançamento do último dia entra no mês e no saldo previsto', function () {
    Transaction::factory()->create(['account_id' => $this->account->id, 'amount' => 3000, 'date' => '2026-11-30', 'paid_at' => '2026-11-30']);
    Transaction::factory()->pending()->create(['account_id' => $this->account->id, 'amount' => 2000, 'date' => '2026-11-30']);

    $summary = app(MonthSummary::class);
    $month = CarbonImmutable::parse('2026-11-01');

    expect($summary->totals($month)['expense'])->toBe(5000)
        ->and($summary->currentTotal())->toBe(7000)
        ->and($summary->projectedTotal($month))->toBe(5000);
});
