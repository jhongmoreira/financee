<?php

use App\Livewire\Reports\Index;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Services\ReportService;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-11-15 10:00');
    $this->actingAs($this->user = User::factory()->create());
    $this->account = Account::create(['name' => 'Nubank', 'type' => 'checking', 'color' => '#820ad1', 'initial_balance' => 100000]);
    $this->food = Category::create(['type' => 'expense', 'name' => 'Alimentação', 'icon' => 'shopping-cart', 'color' => '#ea580c']);
    $this->market = Category::create(['type' => 'expense', 'name' => 'Mercado', 'icon' => 'shopping-cart', 'color' => '#ea580c', 'parent_id' => $this->food->id]);
    $this->fun = Category::create(['type' => 'expense', 'name' => 'Lazer', 'icon' => 'ticket', 'color' => '#c026d3']);

    $add = fn ($cat, $amount, $date, $desc = 'X') => Transaction::factory()->create(['account_id' => $this->account->id, 'category_id' => $cat?->id, 'amount' => $amount, 'date' => $date, 'paid_at' => $date, 'description' => $desc]);
    $add($this->market, 30000, '2026-11-03', 'Feira da semana');
    $add($this->food, 10000, '2026-11-04');
    $add($this->fun, 20000, '2026-11-05');
    $add(null, 5000, '2026-11-06');
    $add($this->market, 20000, '2026-10-10'); // período anterior
});

afterEach(fn () => CarbonImmutable::setTestNow());

it('agrupa por categoria principal com subcategorias e compara com o período anterior', function () {
    [$start, $end] = ReportService::presetRange('month');
    $rows = app(ReportService::class)->byCategory('expense', $start, $end);

    expect(array_column($rows, 'name'))->toBe(['Alimentação', 'Lazer', 'Sem categoria'])
        ->and($rows[0])->toMatchArray(['total' => 40000, 'pct' => 61.5, 'previous' => 20000, 'change' => 100.0])
        ->and($rows[0]['children'])->toBe([['name' => 'Mercado', 'total' => 30000], ['name' => 'Alimentação (geral)', 'total' => 10000]]);
});

it('histórico de saldo usa só efetivados até o fim de cada mês', function () {
    $rows = app(ReportService::class)->balanceHistory(CarbonImmutable::parse('2026-10-01'), CarbonImmutable::parse('2026-11-30'));

    expect(array_column($rows, 'balance'))->toBe([80000, 15000]);
});

it('exibe todas as abas de relatório', function (string $tab, string $text) {
    Livewire::test(Index::class)->set('tab', $tab)->assertOk()->assertSee($text);
})->with([
    ['despesas', 'Alimentação'],
    ['receitas', 'Sem receitas no período'],
    ['evolucao', 'Média/mês'],
    ['saldo', 'Saldo geral efetivado'],
    ['cartoes', 'Nenhuma fatura no período'],
]);

it('exporta CSV para Excel pt-BR', function () {
    $response = $this->get(route('export.csv', ['inicio' => '2026-11-01', 'fim' => '2026-11-30']));

    $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    $csv = $response->streamedContent();

    expect($csv)->toStartWith("\xEF\xBB\xBF")
        ->and($csv)->toContain('Data;Descrição;Categoria;Subcategoria')
        ->and($csv)->toContain('03/11/2026;"Feira da semana";Alimentação;Mercado;Nubank;Despesa;-300,00;Efetivado')
        ->and(substr_count($csv, "\n"))->toBe(5); // cabeçalho + 4 de novembro
});

it('gera o PDF do relatório', function () {
    $response = $this->get(route('export.pdf', ['inicio' => '2026-11-01', 'fim' => '2026-11-30']));

    $response->assertOk()->assertHeader('content-type', 'application/pdf');
    expect(substr($response->getContent(), 0, 4))->toBe('%PDF');
});

it('backup JSON contém só os dados do usuário', function () {
    $other = User::factory()->create();
    $foreign = new Account(['name' => 'Conta alheia', 'type' => 'checking', 'color' => '#000000']);
    $foreign->user_id = $other->id;
    $foreign->save();

    $json = json_decode($this->get(route('export.backup'))->assertOk()->streamedContent(), true);

    expect(collect($json['accounts'])->pluck('name')->all())->toBe(['Nubank'])
        ->and($json['transactions'])->toHaveCount(5);
});

it('exportação exige login', function () {
    auth()->logout();
    $this->get(route('export.csv'))->assertRedirect('/login');
});

it('ignora valores inválidos vindos do navegador', function () {
    Livewire::test(Index::class)->set('tab', 'hackeado')->assertOk()->assertSet('tab', 'despesas')
        ->set('preset', 'xyz')->assertSet('preset', 'month')
        ->set('regime', 'abc')->assertSet('regime', 'competencia');
});
