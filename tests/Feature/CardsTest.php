<?php

use App\Livewire\Cards\Index;
use App\Models\Account;
use App\Models\CreditCard;
use App\Models\User;
use Livewire\Livewire;

beforeEach(fn () => $this->actingAs($this->user = User::factory()->create()));

it('cria um cartão', function () {
    $account = Account::create(['name' => 'Nubank', 'type' => 'checking', 'color' => '#820ad1']);

    Livewire::test(Index::class)
        ->call('create')
        ->assertSet('payment_account_id', (string) $account->id)
        ->set('name', 'Roxinho')->set('brand', 'mastercard')->set('limit', 500000)
        ->set('closing_day', 3)->set('due_day', 10)
        ->call('save')->assertHasNoErrors();

    expect(CreditCard::firstOrFail())
        ->name->toBe('Roxinho')->limit->toBe(500000)->closing_day->toBe(3)->due_day->toBe(10)
        ->payment_account_id->toBe($account->id);
});

it('valida dias e conta de pagamento de outro usuário', function () {
    $other = User::factory()->create();
    $foreign = new Account(['name' => 'Alheia', 'type' => 'checking', 'color' => '#059669']);
    $foreign->user_id = $other->id;
    $foreign->save();

    Livewire::test(Index::class)->call('create')
        ->set('name', 'X')->set('closing_day', 32)->set('due_day', 0)->set('payment_account_id', (string) $foreign->id)
        ->call('save')
        ->assertHasErrors(['closing_day', 'due_day', 'payment_account_id']);
});

it('edita, arquiva e exclui', function () {
    $card = CreditCard::create(['name' => 'Visa', 'brand' => 'visa', 'limit' => 1000, 'closing_day' => 1, 'due_day' => 8, 'color' => '#2563eb']);

    $component = Livewire::test(Index::class)->call('edit', $card->id)->set('limit', 2000)->call('save');
    expect($card->fresh()->limit)->toBe(2000);

    $component->call('edit', $card->id)->call('toggleArchive');
    expect($card->fresh()->archived_at)->not->toBeNull();

    $component->call('edit', $card->id)->call('delete');
    expect(CreditCard::find($card->id))->toBeNull();
});

it('exibe a tela', function () {
    CreditCard::create(['name' => 'Meu Visa', 'brand' => 'visa', 'limit' => 300000, 'closing_day' => 5, 'due_day' => 12, 'color' => '#2563eb']);
    $this->get('/cartoes')->assertOk()->assertSee('Meu Visa')->assertSee('R$ 3.000,00');
});
