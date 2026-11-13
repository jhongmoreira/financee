<?php

use App\Livewire\Accounts\Index as AccountsIndex;
use App\Livewire\Cards\Index as CardsIndex;
use App\Models\Account;
use App\Models\CreditCard;
use App\Models\InvestmentAsset;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Livewire\Livewire;

it('envia cabeçalhos de segurança', function () {
    $this->get('/login')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeaderMissing('Strict-Transport-Security'); // só em produção

    expect($this->get('/login')->headers->get('Content-Security-Policy'))
        ->toContain("default-src 'self'")->toContain("frame-ancestors 'none'")->toContain("object-src 'none'");
});

it('mostra página 404 em português', function () {
    $this->actingAs(User::factory()->create())->get('/nao-existe')->assertNotFound()->assertSee('Página não encontrada');
});

it('reordena contas e cartões', function () {
    $this->actingAs(User::factory()->create());
    $a = Account::create(['name' => 'A', 'type' => 'checking', 'color' => '#059669']);
    $b = Account::create(['name' => 'B', 'type' => 'checking', 'color' => '#059669']);
    $c = Account::create(['name' => 'C', 'type' => 'checking', 'color' => '#059669']);

    Livewire::test(AccountsIndex::class)->call('move', $c->id, -1)->call('move', $c->id, -1)->call('move', $a->id, -1);
    expect(Account::query()->ordered()->pluck('name')->all())->toBe(['A', 'C', 'B']);

    $x = CreditCard::create(['name' => 'X', 'brand' => 'visa', 'limit' => 1, 'closing_day' => 1, 'due_day' => 8, 'color' => '#000000']);
    $y = CreditCard::create(['name' => 'Y', 'brand' => 'visa', 'limit' => 1, 'closing_day' => 1, 'due_day' => 8, 'color' => '#000000']);
    Livewire::test(CardsIndex::class)->call('move', $y->id, -1)->call('move', $y->id, 1)->call('move', $x->id, 1);
    expect(CreditCard::query()->ordered()->pluck('name')->all())->toBe(['Y', 'X']);
});

it('seeder de demonstração gera dados consistentes e todas as telas abrem', function () {
    $this->seed(DemoSeeder::class);
    $user = User::where('email', 'demo@financee.test')->firstOrFail();
    $this->actingAs($user);

    foreach (['/', '/lancamentos', '/planejamento', '/planejamento?aba=previsao', '/mais', '/perfil', '/contas', '/cartoes', '/categorias',
        '/fixos', '/investimentos', '/relatorios', '/relatorios?relatorio=evolucao', '/relatorios?relatorio=saldo', '/relatorios?relatorio=cartoes', '/notificacoes'] as $url) {
        $this->get($url)->assertOk();
    }

    $card = CreditCard::firstOrFail();
    $this->get(route('cards.invoice', $card))->assertOk();
    $this->get(route('investments.asset', InvestmentAsset::firstOrFail()))->assertOk();
});

it('a limpeza de contas de teste automatizado não afeta usuários reais', function () {
    $real = User::factory()->create(['email' => 'pessoa@gmail.com']);
    $bot = User::factory()->create(['email' => 'teste-automatico-123@financee.test']);

    (require database_path('migrations/2026_11_13_190000_remove_automated_test_accounts.php'))->up();

    expect(User::find($real->id))->not->toBeNull()->and(User::find($bot->id))->toBeNull();
});
