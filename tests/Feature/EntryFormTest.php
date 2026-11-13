<?php

use App\Livewire\EntryForm;
use App\Models\Account;
use App\Models\Category;
use App\Models\CreditCard;
use App\Models\Transaction;
use App\Models\Transfer;
use App\Models\User;
use App\Services\InvoiceService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-11-15 10:00');
    $this->actingAs($this->user = User::factory()->create());
    $this->account = Account::create(['name' => 'Nubank', 'type' => 'checking', 'color' => '#820ad1']);
    $this->other = Account::create(['name' => 'Itaú', 'type' => 'checking', 'color' => '#ec7000']);
    $this->food = Category::create(['type' => 'expense', 'name' => 'Alimentação', 'icon' => 'shopping-cart', 'color' => '#ea580c']);
    $this->salary = Category::create(['type' => 'income', 'name' => 'Salário', 'icon' => 'briefcase', 'color' => '#059669']);
});

afterEach(fn () => CarbonImmutable::setTestNow());

it('lança uma despesa paga hoje', function () {
    Livewire::test(EntryForm::class)
        ->call('open', 'expense')
        ->assertSet('date', '2026-11-15')->assertSet('paid', true)
        ->set('amount', 4590)->set('description', ' Mercado ')->set('category_id', (string) $this->food->id)
        ->set('account_id', (string) $this->account->id)
        ->call('save')
        ->assertHasNoErrors()->assertDispatched('entry-saved')->assertDispatched('close-sheet');

    expect(Transaction::firstOrFail())
        ->type->toBe('expense')->amount->toBe(4590)->description->toBe('Mercado')
        ->paid_at->toDateString()->toBe('2026-11-15')->user_id->toBe($this->user->id);
});

it('data futura vira pendente automaticamente', function () {
    Livewire::test(EntryForm::class)->call('open', 'expense')
        ->set('date', '2026-11-30')->assertSet('paid', false)
        ->set('amount', 1000)->set('description', 'Aluguel')->set('account_id', (string) $this->account->id)
        ->call('save')->assertHasNoErrors();

    expect(Transaction::first()->paid_at)->toBeNull();
});

it('valida valor, descrição, conta e categoria do tipo certo', function () {
    Livewire::test(EntryForm::class)->call('open', 'expense')
        ->set('amount', 0)->set('description', '')->set('account_id', null)->set('category_id', (string) $this->salary->id)
        ->call('save')
        ->assertHasErrors(['amount', 'description', 'account_id', 'category_id']);

    expect(Transaction::count())->toBe(0);
});

it('não aceita conta de outro usuário', function () {
    $foreign = new Account(['name' => 'X', 'type' => 'checking', 'color' => '#059669']);
    $foreign->user_id = User::factory()->create()->id;
    $foreign->save();

    Livewire::test(EntryForm::class)->call('open', 'expense')
        ->set('amount', 100)->set('description', 'X')->set('account_id', (string) $foreign->id)
        ->call('save')->assertHasErrors('account_id');
});

it('salvar e criar outro mantém conta, categoria e data', function () {
    Livewire::test(EntryForm::class)->call('open', 'expense')
        ->set('amount', 1000)->set('description', 'Café')->set('category_id', (string) $this->food->id)->set('account_id', (string) $this->account->id)
        ->call('save', true)
        ->assertSet('amount', 0)->assertSet('description', '')
        ->assertSet('category_id', (string) $this->food->id)->assertSet('account_id', (string) $this->account->id)
        ->assertNotDispatched('close-sheet');

    expect(Transaction::count())->toBe(1);
});

it('sugere categoria e conta pela descrição já usada', function () {
    Transaction::factory()->create(['description' => 'Uber', 'category_id' => $this->food->id, 'account_id' => $this->other->id]);

    Livewire::test(EntryForm::class)->call('open', 'expense')
        ->set('description', 'Uber')
        ->assertSet('category_id', (string) $this->food->id)
        ->assertSet('account_id', (string) $this->other->id);
});

it('cria transferência e valida contas iguais', function () {
    Livewire::test(EntryForm::class)->call('open', 'transfer')
        ->set('amount', 50000)->set('account_id', (string) $this->account->id)->set('to_account_id', (string) $this->account->id)
        ->call('save')->assertHasErrors('to_account_id')
        ->set('to_account_id', (string) $this->other->id)
        ->call('save')->assertHasNoErrors();

    expect(Transfer::firstOrFail())->from_account_id->toBe($this->account->id)->to_account_id->toBe($this->other->id)->amount->toBe(50000);
});

it('edita e exclui um lançamento', function () {
    $t = Transaction::factory()->create(['account_id' => $this->account->id, 'amount' => 1000]);

    $component = Livewire::test(EntryForm::class)->call('edit', 'transaction', $t->id)
        ->assertSet('amount', 1000)->assertDispatched('open-sheet')
        ->set('amount', 2500)->call('save')->assertHasNoErrors();
    expect($t->fresh()->amount)->toBe(2500);

    $component->call('edit', 'transaction', $t->id)->call('delete');
    expect(Transaction::count())->toBe(0);
});

it('não edita lançamento de outro usuário', function () {
    $t = new Transaction(['type' => 'expense', 'description' => 'X', 'amount' => 1, 'date' => '2026-11-01']);
    $t->user_id = User::factory()->create()->id;
    $t->save();

    expect(fn () => Livewire::test(EntryForm::class)->call('edit', 'transaction', $t->id))
        ->toThrow(ModelNotFoundException::class);
});

it('ignora tipo inválido vindo do navegador', function () {
    Livewire::test(EntryForm::class)->call('open', 'expense')
        ->set('kind', 'hackeado')->assertSet('kind', 'expense')
        ->set('amount', 100)->set('description', 'X')->set('account_id', (string) $this->account->id)
        ->call('save')->assertHasNoErrors();

    expect(Transaction::first()->type)->toBe('expense');
});

it('sugere as descrições recentes do mesmo tipo e meio, sem repetir', function () {
    $card = CreditCard::create(['name' => 'C', 'brand' => 'visa', 'limit' => 100000, 'closing_day' => 3, 'due_day' => 10, 'color' => '#7c3aed']);
    foreach (['Padaria' => '2026-11-01', 'Farmácia' => '2026-11-10', 'padaria ' => '2026-11-12', 'Mercado' => '2026-11-14'] as $desc => $date) {
        Transaction::factory()->create(['description' => $desc, 'account_id' => $this->account->id, 'date' => $date]);
    }
    Transaction::factory()->income()->create(['description' => 'Salário', 'account_id' => $this->account->id]);
    app(InvoiceService::class)->createPurchase($card, 'Netflix', null, CarbonImmutable::parse('2026-11-05'), 3990);

    $expense = Livewire::test(EntryForm::class)->call('open', 'expense')->viewData('suggestions');
    expect($expense)->toBe(['Mercado', 'padaria ', 'Farmácia']);

    expect(Livewire::test(EntryForm::class)->call('open', 'income')->viewData('suggestions'))->toBe(['Salário'])
        ->and(Livewire::test(EntryForm::class)->call('open', 'card')->viewData('suggestions'))->toBe(['Netflix']);
});

it('lançamentos futuros não entram nas descrições recentes', function () {
    Transaction::factory()->create(['description' => 'Café', 'account_id' => $this->account->id, 'date' => '2026-11-14']);
    Transaction::factory()->pending()->create(['description' => 'Aluguel futuro', 'account_id' => $this->account->id, 'date' => '2027-05-10']);

    expect(Livewire::test(EntryForm::class)->call('open', 'expense')->viewData('suggestions'))->toBe(['Café']);
});
