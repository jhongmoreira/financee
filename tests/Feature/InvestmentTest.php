<?php

use App\Livewire\Investments\Asset as AssetPage;
use App\Livewire\Investments\Index;
use App\Livewire\Transactions\Index as Statement;
use App\Models\Account;
use App\Models\InvestmentAsset;
use App\Models\InvestmentMovement;
use App\Models\InvestmentPortfolio;
use App\Models\User;
use App\Services\InvestmentService;
use App\Services\MonthSummary;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-11-15 10:00');
    $this->actingAs(User::factory()->create());
    $this->account = Account::create(['name' => 'Conta', 'type' => 'checking', 'color' => '#059669', 'initial_balance' => 1000000]);
    $this->portfolio = InvestmentPortfolio::create(['name' => 'XP', 'color' => '#111111']);
    $this->service = app(InvestmentService::class);
});

afterEach(fn () => CarbonImmutable::setTestNow());

function movement(InvestmentAsset $asset, string $type, int $amount, string $date, ?int $account = null): void
{
    InvestmentMovement::create(['asset_id' => $asset->id, 'type' => $type, 'amount' => $amount, 'date' => $date, 'account_id' => $account]);
}

it('reconstrói o valor atual e calcula a rentabilidade', function () {
    $asset = InvestmentAsset::create(['portfolio_id' => $this->portfolio->id, 'name' => 'CDB', 'class' => 'cdb']);
    movement($asset, 'contribution', 100000, '2026-01-10');
    movement($asset, 'contribution', 50000, '2026-03-10');
    movement($asset, 'valuation', 160000, '2026-06-30');
    movement($asset, 'withdrawal', 20000, '2026-07-10');
    movement($asset, 'income', 1000, '2026-08-01');                    // reinvestido
    movement($asset, 'income', 500, '2026-08-01', $this->account->id); // pago em conta
    $this->service->recalculate($asset);

    $stats = $this->service->stats(collect([$asset->fresh()]))[$asset->id];

    expect($asset->fresh()->current_value)->toBe(141000)
        ->and($stats)->toMatchArray(['contributed' => 150000, 'withdrawn' => 20000, 'cash_income' => 500, 'invested' => 130000, 'gain' => 11500])
        ->and($stats['pct'])->toBe(7.67);
});

it('aporte com conta sai do saldo, mas não é despesa nem entra no saldo geral como investimento', function () {
    $asset = InvestmentAsset::create(['portfolio_id' => $this->portfolio->id, 'name' => 'Tesouro', 'class' => 'treasury']);
    movement($asset, 'contribution', 300000, '2026-11-10', $this->account->id);
    movement($asset, 'withdrawal', 50000, '2026-11-12', $this->account->id);
    $this->service->recalculate($asset);

    $summary = app(MonthSummary::class);
    expect($this->account->balance())->toBe(1000000 - 300000 + 50000)
        ->and($summary->currentTotal())->toBe(750000) // investimento fica separado
        ->and($summary->totals(CarbonImmutable::parse('2026-11-01'))['expense'])->toBe(0)
        ->and($this->service->totalValue())->toBe(250000);
});

it('cria carteira e ativo com aporte inicial pela tela', function () {
    Livewire::test(Index::class)
        ->call('createAsset', $this->portfolio->id)
        ->set('asset_name', 'CDB Banco X')->set('asset_class', 'cdb')->set('asset_rate', '110% CDI')
        ->set('asset_amount', 500000)->set('asset_account_id', (string) $this->account->id)
        ->call('saveAsset')->assertHasNoErrors()->assertRedirect();

    $asset = InvestmentAsset::firstOrFail();
    expect($asset->current_value)->toBe(500000)->and($this->account->balance())->toBe(500000);

    Livewire::test(Index::class)->assertSee('CDB Banco X')->assertSee('R$ 5.000,00')->assertSee('110% CDI');
});

it('registra rendimento e atualização pela página do ativo', function () {
    $asset = InvestmentAsset::create(['portfolio_id' => $this->portfolio->id, 'name' => 'FII', 'class' => 'fii', 'ticker' => 'HGLG11']);
    movement($asset, 'contribution', 100000, '2026-10-01');
    $this->service->recalculate($asset);

    Livewire::test(AssetPage::class, ['asset' => $asset])
        ->call('newMovement', 'valuation')->assertSet('amount', 100000)
        ->set('amount', 105000)->call('saveMovement')->assertHasNoErrors()
        ->call('newMovement', 'income')->set('amount', 800)->set('account_id', (string) $this->account->id)->call('saveMovement')
        ->assertSee('R$ 1.050,00');

    expect($asset->fresh()->current_value)->toBe(105000)->and($this->account->balance())->toBe(1000800);
});

it('extrato mostra movimentação de investimento na conta', function () {
    $asset = InvestmentAsset::create(['portfolio_id' => $this->portfolio->id, 'name' => 'Ações BR', 'class' => 'stock']);
    movement($asset, 'contribution', 1000, '2026-11-05', $this->account->id);

    Livewire::test(Statement::class)->assertSee('Aporte: Ações BR');
});

it('isola carteiras entre usuários', function () {
    $other = User::factory()->create();
    $foreign = new InvestmentPortfolio(['name' => 'Alheia', 'color' => '#000000']);
    $foreign->user_id = $other->id;
    $foreign->save();
    $asset = new InvestmentAsset(['portfolio_id' => $foreign->id, 'name' => 'X', 'class' => 'other']);
    $asset->user_id = $other->id;
    $asset->save();

    $this->get(route('investments.asset', $asset->id))->assertNotFound();
    Livewire::test(Index::class)->call('createAsset')->set('asset_portfolio_id', (string) $foreign->id)
        ->set('asset_name', 'Y')->call('saveAsset')->assertHasErrors('asset_portfolio_id');
});

it('o formulário mostra o texto certo para cada tipo de movimento', function () {
    $asset = InvestmentAsset::create(['portfolio_id' => $this->portfolio->id, 'name' => 'CDB', 'class' => 'cdb']);

    Livewire::test(AssetPage::class, ['asset' => $asset])
        ->call('newMovement', 'income')
        ->assertSee('Valor do rendimento')->assertSee('Só o valor recebido')->assertDontSee('Valor atual do investimento')
        ->call('newMovement', 'valuation')
        ->assertSee('Valor atual do investimento')->assertDontSee('Valor do rendimento')
        ->call('newMovement', 'contribution')
        ->assertSee('Soma ao valor do investimento')->assertDontSee('Valor atual do investimento');
});

it('corrige um rendimento lançado por engano trocando o tipo para atualização', function () {
    $asset = InvestmentAsset::create(['portfolio_id' => $this->portfolio->id, 'name' => 'CDB', 'class' => 'cdb']);
    movement($asset, 'contribution', 200000, '2026-11-13');
    movement($asset, 'income', 200109, '2026-11-13'); // o engano: total lançado como rendimento
    $this->service->recalculate($asset);
    expect($asset->fresh()->current_value)->toBe(400109);

    $wrong = InvestmentMovement::where('type', 'income')->firstOrFail();
    Livewire::test(AssetPage::class, ['asset' => $asset])
        ->call('editMovement', $wrong->id)->assertSee('Editar rendimento')
        ->set('type', 'valuation')->assertSee('Valor atual do investimento')
        ->call('saveMovement')->assertHasNoErrors();

    $stats = $this->service->stats(collect([$asset->fresh()]))[$asset->id];
    expect($asset->fresh()->current_value)->toBe(200109)->and($stats['gain'])->toBe(109);
});
