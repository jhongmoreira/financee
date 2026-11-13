<?php

use App\Livewire\Accounts\Index;
use App\Models\Account;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

beforeEach(fn () => $this->actingAs($this->user = User::factory()->create()));

it('cria uma conta com saldo em centavos', function () {
    $nubank = Institution::where('name', 'Nubank')->first();

    Livewire::test(Index::class)
        ->call('create')
        ->set('institution_id', (string) $nubank->id)
        ->assertSet('name', 'Nubank')
        ->assertSet('color', $nubank->color)
        ->set('initial_balance', 150075)
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('toast');

    $account = Account::firstOrFail();
    expect($account)->name->toBe('Nubank')->initial_balance->toBe(150075)->user_id->toBe($this->user->id)
        ->and($account->balance())->toBe(150075);
});

it('aceita saldo negativo e valida campos', function () {
    Livewire::test(Index::class)->call('create')->set('name', '')->call('save')->assertHasErrors('name');

    Livewire::test(Index::class)->call('create')
        ->set('name', 'Cheque especial')->set('initial_balance', -5000)->set('type', 'xyz')
        ->call('save')->assertHasErrors('type');

    Livewire::test(Index::class)->call('create')
        ->set('name', 'Cheque especial')->set('initial_balance', -5000)
        ->call('save')->assertHasNoErrors();

    expect(Account::first()->initial_balance)->toBe(-5000);
});

it('edita, arquiva e exclui', function () {
    $account = Account::create(['name' => 'Itaú', 'type' => 'checking', 'color' => '#ec7000']);

    $component = Livewire::test(Index::class)->call('edit', $account->id)
        ->assertSet('name', 'Itaú')->set('name', 'Itaú Personnalité')->call('save');
    expect($account->fresh()->name)->toBe('Itaú Personnalité');

    $component->call('edit', $account->id)->call('toggleArchive');
    expect($account->fresh()->archived_at)->not->toBeNull();

    $component->call('edit', $account->id)->call('delete');
    expect(Account::find($account->id))->toBeNull();
});

it('soma no total só as contas ativas marcadas para o total', function () {
    Account::create(['name' => 'A', 'type' => 'checking', 'color' => '#059669', 'initial_balance' => 10000]);
    Account::create(['name' => 'B', 'type' => 'checking', 'color' => '#059669', 'initial_balance' => 5000, 'include_in_total' => false]);
    Account::create(['name' => 'C', 'type' => 'checking', 'color' => '#059669', 'initial_balance' => 7000, 'archived_at' => now()]);

    expect(Livewire::test(Index::class)->get('total'))->toBe(10000);
});

it('não permite editar conta de outro usuário', function () {
    $other = User::factory()->create();
    $foreign = new Account(['name' => 'Alheia', 'type' => 'checking', 'color' => '#059669']);
    $foreign->user_id = $other->id;
    $foreign->save();

    expect(fn () => Livewire::test(Index::class)->call('edit', $foreign->id))
        ->toThrow(ModelNotFoundException::class);
});

it('exibe a tela', function () {
    Account::create(['name' => 'Conta X', 'type' => 'checking', 'color' => '#059669', 'initial_balance' => 12345]);
    $this->get('/contas')->assertOk()->assertSee('Conta X')->assertSee('R$ 123,45');
});
