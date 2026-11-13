<?php

use App\Livewire\Profile;
use App\Models\User;
use App\Services\DefaultUserData;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

const RESET_TABLES = ['transactions', 'transfers', 'invoice_payments', 'investment_movements', 'budgets', 'invoices',
    'installment_plans', 'recurrences', 'investment_assets', 'investment_portfolios', 'credit_cards', 'accounts', 'categories'];

function countsFor(User $user): array
{
    return collect(RESET_TABLES)->mapWithKeys(fn ($t) => [$t => DB::table($t)->where('user_id', $user->id)->count()])->all();
}

beforeEach(function () {
    $this->seed(DemoSeeder::class);
    $this->user = User::where('email', 'demo@financee.test')->firstOrFail();
    $this->user->update(['password' => 'senha-atual-123', 'settings' => ['theme' => 'dark']]);
    $this->user->updatePushSubscription('https://fcm.googleapis.com/fcm/send/x', 'k', 'a');
});

it('apaga todos os dados e recria a estrutura inicial, mantendo login e preferências', function () {
    $before = countsFor($this->user);
    expect($before['transactions'])->toBeGreaterThan(50)->and($before['investment_assets'])->toBeGreaterThan(0)->and($before['budgets'])->toBeGreaterThan(0);

    $this->actingAs($this->user);
    Livewire::test(Profile::class)->set('reset_password', 'senha-atual-123')->call('resetData')
        ->assertHasNoErrors()->assertRedirect(route('home'));

    $after = countsFor($this->user);
    $categories = $after['categories'];
    unset($after['categories'], $after['accounts']);

    expect(array_filter($after))->toBe([])
        ->and(DB::table('accounts')->where('user_id', $this->user->id)->pluck('name')->all())->toBe(['Carteira'])
        ->and($categories)->toBe(collect(DefaultUserData::EXPENSES)->sum(fn ($c) => 1 + count($c[3])) + count(DefaultUserData::INCOMES))
        ->and(User::find($this->user->id))->not->toBeNull()
        ->and($this->user->fresh()->theme())->toBe('dark')
        ->and($this->user->pushSubscriptions()->count())->toBe(1);

    $this->assertAuthenticatedAs($this->user);
    $this->get('/')->assertOk();
});

it('exige a senha correta', function () {
    $before = countsFor($this->user);

    $this->actingAs($this->user);
    Livewire::test(Profile::class)->set('reset_password', 'errada')->call('resetData')->assertHasErrors('reset_password');
    Livewire::test(Profile::class)->set('reset_password', '')->call('resetData')->assertHasErrors('reset_password');

    expect(countsFor($this->user))->toBe($before);
});

it('não afeta os dados de outros usuários', function () {
    $other = User::factory()->create(['password' => 'outra-senha-123']);
    app(DefaultUserData::class)->createFor($other);
    $otherBefore = countsFor($other);
    $demoBefore = countsFor($this->user);

    $this->actingAs($other);
    Livewire::test(Profile::class)->set('reset_password', 'outra-senha-123')->call('resetData')->assertHasNoErrors();

    expect(countsFor($this->user))->toBe($demoBefore)->and(countsFor($other))->toBe($otherBefore);
});

it('mostra a opção no perfil', function () {
    $this->actingAs($this->user)->get('/perfil')->assertOk()->assertSee('Recomeçar do zero')->assertSee('Apagar tudo e recomeçar');
});

it('liga o secure_delete do SQLite em toda conexão', function () {
    expect((int) DB::selectOne('PRAGMA secure_delete')->secure_delete)->toBe(1);
});
