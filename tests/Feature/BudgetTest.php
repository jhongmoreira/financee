<?php

use App\Livewire\Home;
use App\Livewire\Planning;
use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\CreditCard;
use App\Models\Transaction;
use App\Models\User;
use App\Services\BudgetService;
use App\Services\InvoiceService;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-11-15 10:00');
    $this->actingAs(User::factory()->create());
    $this->account = Account::create(['name' => 'Conta', 'type' => 'checking', 'color' => '#059669']);
    $this->food = Category::create(['type' => 'expense', 'name' => 'Alimentação', 'icon' => 'shopping-cart', 'color' => '#ea580c']);
    $this->market = Category::create(['type' => 'expense', 'name' => 'Mercado', 'icon' => 'shopping-cart', 'color' => '#ea580c', 'parent_id' => $this->food->id]);
    $this->fun = Category::create(['type' => 'expense', 'name' => 'Lazer', 'icon' => 'ticket', 'color' => '#c026d3']);
    $this->service = app(BudgetService::class);
});

afterEach(fn () => CarbonImmutable::setTestNow());

it('soma gasto da categoria e subcategorias, em conta e cartão, efetivado e previsto', function () {
    $card = CreditCard::create(['name' => 'C', 'brand' => 'visa', 'limit' => 100000, 'closing_day' => 3, 'due_day' => 10, 'color' => '#7c3aed']);
    Transaction::factory()->create(['account_id' => $this->account->id, 'category_id' => $this->food->id, 'amount' => 10000, 'date' => '2026-11-02']);
    Transaction::factory()->pending()->create(['account_id' => $this->account->id, 'category_id' => $this->market->id, 'amount' => 20000, 'date' => '2026-11-25']);
    app(InvoiceService::class)->createPurchase($card, 'Restaurante', $this->market->id, CarbonImmutable::parse('2026-11-10'), 15000);
    Transaction::factory()->create(['account_id' => $this->account->id, 'category_id' => $this->food->id, 'amount' => 99999, 'date' => '2026-10-30']);
    $this->service->set($this->food, 50000, null);

    $row = $this->service->forMonth(CarbonImmutable::parse('2026-11-01'))->first();

    expect($row)->toMatchArray(['budget' => 50000, 'spent' => 45000, 'paid' => 25000, 'remaining' => 5000, 'pct' => 90, 'is_default' => true]);
});

it('orçamento do mês substitui o padrão e copia do mês anterior', function () {
    $this->service->set($this->food, 50000, null);
    $this->service->set($this->food, 70000, '2026-11');

    expect($this->service->forMonth(CarbonImmutable::parse('2026-11-01'))->first()['budget'])->toBe(70000)
        ->and($this->service->forMonth(CarbonImmutable::parse('2026-12-01'))->first()['budget'])->toBe(50000);

    expect($this->service->copyFromPrevious(CarbonImmutable::parse('2026-12-01')))->toBe(1)
        ->and(Budget::where('month', '2026-12')->value('amount'))->toBe(70000);
});

it('sugere pela média dos últimos 3 meses', function () {
    foreach (['2026-08-10' => 30000, '2026-09-10' => 30000, '2026-10-10' => 45000] as $date => $amount) {
        Transaction::factory()->create(['account_id' => $this->account->id, 'category_id' => $this->market->id, 'amount' => $amount, 'date' => $date]);
    }

    expect($this->service->suggestions(CarbonImmutable::parse('2026-11-01')))->toBe([$this->food->id => 35000]);
});

it('cria, edita e remove orçamento pela tela', function () {
    Livewire::test(Planning::class)
        ->call('editBudget')->set('budget_category_id', (string) $this->fun->id)->set('budget_amount', 30000)
        ->call('saveBudget')->assertHasNoErrors()
        ->assertSee('Lazer')->assertSee('R$ 300,00');

    Livewire::test(Planning::class)->call('editBudget', $this->fun->id)->assertSet('budget_amount', 30000)
        ->set('budget_scope', 'month')->set('budget_amount', 10000)->call('saveBudget');
    expect(Budget::where('month', '2026-11')->value('amount'))->toBe(10000);

    Livewire::test(Planning::class)->call('editBudget', $this->fun->id)->call('removeBudget');
    expect(Budget::count())->toBe(0);
});

it('não aceita subcategoria nem categoria de receita no orçamento', function () {
    $income = Category::create(['type' => 'income', 'name' => 'Salário', 'icon' => 'briefcase', 'color' => '#059669']);

    Livewire::test(Planning::class)->call('editBudget')->set('budget_category_id', (string) $this->market->id)->set('budget_amount', 100)
        ->call('saveBudget')->assertHasErrors('budget_category_id')
        ->set('budget_category_id', (string) $income->id)->call('saveBudget')->assertHasErrors('budget_category_id');
});

it('aba de previsão mostra o gráfico', function () {
    Livewire::test(Planning::class)->set('tab', 'previsao')->assertSee('Saldo geral ao fim de cada mês')->assertSee('x-data="chart(', false);
});

it('Início mostra gráficos e orçamentos', function () {
    Transaction::factory()->create(['account_id' => $this->account->id, 'category_id' => $this->market->id, 'amount' => 12000, 'date' => '2026-11-05']);
    $this->service->set($this->food, 20000, null);

    Livewire::test(Home::class)
        ->assertSee('Despesas de novembro')->assertSee('Alimentação')
        ->assertSee('Orçamentos')->assertSee('Receitas × despesas');
});

it('últimos lançamentos não mostram parcelas futuras do cartão', function () {
    $card = CreditCard::create(['name' => 'C', 'brand' => 'visa', 'limit' => 900000, 'closing_day' => 3, 'due_day' => 10, 'color' => '#7c3aed']);
    app(InvoiceService::class)->createPurchase($card, 'Geladeira', null, CarbonImmutable::today(), 300000, 3);

    $recent = Livewire::test(Home::class)->viewData('recent');
    expect($recent)->toHaveCount(1)->and($recent->first()['description'])->toBe('Geladeira');
});
