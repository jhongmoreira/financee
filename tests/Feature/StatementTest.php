<?php

use App\Livewire\Accounts\Index as AccountsIndex;
use App\Livewire\Categories\Index as CategoriesIndex;
use App\Livewire\Transactions\Index;
use App\Models\Account;
use App\Models\Category;
use App\Models\CreditCard;
use App\Models\Transaction;
use App\Models\Transfer;
use App\Models\User;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-11-15 10:00');
    $this->actingAs(User::factory()->create());
    $this->account = Account::create(['name' => 'Nubank', 'type' => 'checking', 'color' => '#820ad1', 'initial_balance' => 10000]);
    $this->food = Category::create(['type' => 'expense', 'name' => 'Alimentação', 'icon' => 'shopping-cart', 'color' => '#ea580c']);
    $this->market = Category::create(['type' => 'expense', 'name' => 'Mercado', 'icon' => 'shopping-cart', 'color' => '#ea580c', 'parent_id' => $this->food->id]);
});

afterEach(fn () => CarbonImmutable::setTestNow());

it('mostra o extrato do mês com navegação', function () {
    Transaction::factory()->create(['description' => 'Pão de queijo', 'account_id' => $this->account->id, 'date' => '2026-11-10']);
    Transaction::factory()->create(['description' => 'Coisa de outubro', 'account_id' => $this->account->id, 'date' => '2026-10-10']);

    Livewire::test(Index::class)
        ->assertSet('month', '2026-11')->assertSee('Pão de queijo')->assertDontSee('Coisa de outubro')
        ->call('previousMonth')->assertSet('month', '2026-10')->assertSee('Coisa de outubro')
        ->call('nextMonth')->call('nextMonth')->assertSet('month', '2026-12');
});

it('filtra por categoria incluindo subcategorias, tipo, status e busca', function () {
    Transaction::factory()->create(['description' => 'Feira', 'category_id' => $this->market->id, 'account_id' => $this->account->id, 'date' => '2026-11-05']);
    Transaction::factory()->pending()->create(['description' => 'Restaurante', 'category_id' => $this->food->id, 'account_id' => $this->account->id, 'date' => '2026-11-20']);
    Transaction::factory()->income()->create(['description' => 'Salário', 'account_id' => $this->account->id, 'date' => '2026-11-05']);

    Livewire::test(Index::class)->set('category', (string) $this->food->id)
        ->assertSee('Feira')->assertSee('Restaurante')->assertDontSee('Salário');

    Livewire::test(Index::class)->set('type', 'income')->assertSee('Salário')->assertDontSee('Feira');
    Livewire::test(Index::class)->set('status', 'pending')->assertSee('Restaurante')->assertDontSee('Feira');
    Livewire::test(Index::class)->set('search', 'fei')->assertSee('Feira')->assertDontSee('Restaurante');
});

it('mostra transferências e marca pendente como pago', function () {
    $other = Account::create(['name' => 'Poupança', 'type' => 'savings', 'color' => '#059669']);
    Transfer::create(['from_account_id' => $this->account->id, 'to_account_id' => $other->id, 'amount' => 500, 'date' => '2026-11-03', 'paid_at' => '2026-11-03']);
    $t = Transaction::factory()->pending()->create(['description' => 'Luz', 'account_id' => $this->account->id, 'date' => '2026-11-10']);

    Livewire::test(Index::class)
        ->assertSee('Nubank → Poupança')
        ->call('togglePaid', 'transaction', $t->id);

    expect($t->fresh()->paid_at->toDateString())->toBe('2026-11-10');
});

it('não exclui conta com lançamentos e ajusta o saldo', function () {
    Transaction::factory()->create(['account_id' => $this->account->id, 'amount' => 2000, 'date' => '2026-11-01', 'paid_at' => '2026-11-01']);

    Livewire::test(AccountsIndex::class)->call('edit', $this->account->id)
        ->assertSet('real_balance', 8000)
        ->call('delete')->assertDispatched('toast');
    expect(Account::find($this->account->id))->not->toBeNull();

    Livewire::test(AccountsIndex::class)->call('edit', $this->account->id)
        ->set('real_balance', 12345)->call('adjustBalance');

    expect($this->account->fresh()->balance())->toBe(12345)
        ->and(Transaction::where('is_adjustment', true)->first())->type->toBe('income')->amount->toBe(4345);
});

it('ao excluir categoria usada, move os lançamentos', function () {
    $other = Category::create(['type' => 'expense', 'name' => 'Outros', 'icon' => 'tag', 'color' => '#6b7280']);
    $t = Transaction::factory()->create(['category_id' => $this->market->id, 'account_id' => $this->account->id]);

    Livewire::test(CategoriesIndex::class)->call('edit', $this->food->id)
        ->call('delete')->assertHasErrors('replacement_id')
        ->set('replacement_id', (string) $this->market->id)->call('delete')->assertHasErrors('replacement_id') // não pode ser filha
        ->set('replacement_id', (string) $other->id)->call('delete')->assertHasNoErrors();

    expect($t->fresh()->category_id)->toBe($other->id)
        ->and(Category::whereIn('id', [$this->food->id, $this->market->id])->count())->toBe(0);
});

it('exibe a Início com saldo e pendências', function () {
    Transaction::factory()->pending()->create(['description' => 'Internet', 'account_id' => $this->account->id, 'date' => '2026-11-17', 'amount' => 9990]);

    $this->get('/')->assertOk()->assertSee('R$ 100,00')->assertSee('Internet')->assertSee('Contas a pagar e receber');
});

it('não acessa a fatura de cartão de outro usuário pela URL', function () {
    $other = User::factory()->create();
    $card = new CreditCard(['name' => 'Alheio', 'brand' => 'visa', 'limit' => 1, 'closing_day' => 1, 'due_day' => 8, 'color' => '#059669']);
    $card->user_id = $other->id;
    $card->save();

    $this->get(route('cards.invoice', $card->id))->assertNotFound();
});

it('lista em ordem do início do mês por padrão e permite inverter (preferência salva)', function () {
    foreach (['2026-11-20' => 'Vinte', '2026-11-03' => 'Tres', '2026-11-11' => 'Onze'] as $date => $desc) {
        Transaction::factory()->create(['description' => $desc, 'account_id' => $this->account->id, 'date' => $date]);
    }

    Livewire::test(Index::class)->assertSet('order', 'asc')->assertSeeInOrder(['Tres', 'Onze', 'Vinte'])
        ->call('toggleOrder')->assertSet('order', 'desc')->assertSeeInOrder(['Vinte', 'Onze', 'Tres']);

    // a escolha fica salva para as próximas vezes
    Livewire::test(Index::class)->assertSet('order', 'desc');
});
