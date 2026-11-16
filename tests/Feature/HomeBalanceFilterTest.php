<?php

use App\Livewire\Home;
use App\Models\Account;
use App\Models\AccountCategory;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs($this->user = User::factory()->create());

    $this->reserva = AccountCategory::create(['name' => 'Reserva de emergência']);
    $this->diaADia = AccountCategory::create(['name' => 'Dia a dia']);

    Account::create(['name' => 'Corrente', 'type' => 'checking', 'color' => '#059669', 'initial_balance' => 10000, 'account_category_id' => $this->diaADia->id]);
    Account::create(['name' => 'Caixinha', 'type' => 'savings', 'color' => '#059669', 'initial_balance' => 50000, 'account_category_id' => $this->reserva->id]);
    // Fora do saldo geral, mas entra quando a categoria dela é filtrada
    Account::create(['name' => 'CDB', 'type' => 'other', 'color' => '#059669', 'initial_balance' => 20000, 'account_category_id' => $this->reserva->id, 'include_in_total' => false]);
    Account::create(['name' => 'Antiga', 'type' => 'savings', 'color' => '#059669', 'initial_balance' => 99900, 'account_category_id' => $this->reserva->id, 'archived_at' => now()]);
    Account::create(['name' => 'Carteira', 'type' => 'wallet', 'color' => '#059669', 'initial_balance' => 3000]);
});

it('sem filtro mostra o saldo geral', function () {
    Livewire::test(Home::class)
        ->assertViewHas('total', 63000)
        ->assertViewHas('balanceFilter', fn (array $f) => ! $f['active'] && count($f['options']) === 3)
        ->assertSee('Saldo geral');
});

it('soma só as contas das categorias selecionadas e guarda a escolha', function () {
    Livewire::test(Home::class)
        ->set('categoryFilter', [(string) $this->reserva->id])
        ->assertViewHas('balanceFilter', fn (array $f) => $f['active'] && $f['total'] === 70000 && $f['count'] === 2 && $f['label'] === 'Reserva de emergência')
        ->assertSee('R$ 700,00')
        ->set('categoryFilter', [(string) $this->reserva->id, '0'])
        ->assertViewHas('balanceFilter', fn (array $f) => $f['total'] === 73000 && $f['count'] === 3);

    expect($this->user->fresh()->setting('home_account_categories'))->toBe([(string) $this->reserva->id, '0']);

    // Volta filtrado ao reabrir a tela; limpar restaura o saldo geral
    Livewire::test(Home::class)
        ->assertSet('categoryFilter', [(string) $this->reserva->id, '0'])
        ->call('clearCategoryFilter')
        ->assertViewHas('balanceFilter', fn (array $f) => ! $f['active']);

    expect($this->user->fresh()->setting('home_account_categories'))->toBe([]);
});

it('ignora categorias inexistentes ou de outro usuário no filtro', function () {
    $foreign = new AccountCategory(['name' => 'Alheia']);
    $foreign->user_id = User::factory()->create()->id;
    $foreign->save();

    Livewire::test(Home::class)
        ->set('categoryFilter', [(string) $foreign->id, '999', 'abc'])
        ->assertSet('categoryFilter', [])
        ->assertViewHas('balanceFilter', fn (array $f) => ! $f['active']);

    // Categoria excluída depois de filtrada: o filtro deixa de valer
    $this->user->updateSetting('home_account_categories', [(string) $this->diaADia->id]);
    $this->diaADia->accounts()->update(['account_category_id' => null]);
    $this->diaADia->delete();

    Livewire::test(Home::class)->assertSet('categoryFilter', []);
});
