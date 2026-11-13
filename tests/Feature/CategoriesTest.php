<?php

use App\Livewire\Categories\Index;
use App\Models\Category;
use App\Models\User;
use Livewire\Livewire;

beforeEach(fn () => $this->actingAs($this->user = User::factory()->create()));

it('cria categoria e subcategoria herdando ícone e cor', function () {
    Livewire::test(Index::class)
        ->call('create')->set('name', 'Viagem')->set('icon', 'paper-airplane')->set('color', '#0284c7')
        ->call('save')->assertHasNoErrors();

    $parent = Category::where('name', 'Viagem')->firstOrFail();
    expect($parent->type)->toBe('expense');

    Livewire::test(Index::class)
        ->call('create', $parent->id)
        ->assertSet('icon', 'paper-airplane')->assertSet('color', '#0284c7')
        ->set('name', 'Hotel')->call('save')->assertHasNoErrors();

    expect(Category::where('name', 'Hotel')->value('parent_id'))->toBe($parent->id);
});

it('cria na aba de receitas', function () {
    Livewire::withQueryParams(['tipo' => 'income'])->test(Index::class)
        ->call('create')->set('name', 'Aluguel recebido')->call('save')->assertHasNoErrors();

    expect(Category::where('name', 'Aluguel recebido')->value('type'))->toBe('income');
});

it('limita a dois níveis', function () {
    $a = Category::create(['type' => 'expense', 'name' => 'A', 'icon' => 'tag', 'color' => '#059669']);
    $b = Category::create(['type' => 'expense', 'name' => 'B', 'icon' => 'tag', 'color' => '#059669', 'parent_id' => $a->id]);
    $c = Category::create(['type' => 'expense', 'name' => 'C', 'icon' => 'tag', 'color' => '#059669']);

    // não pode usar uma subcategoria como pai
    Livewire::test(Index::class)->call('create')->set('name', 'D')->set('parent_id', (string) $b->id)
        ->call('save')->assertHasErrors('parent_id');

    // categoria com filhos não pode virar subcategoria
    Livewire::test(Index::class)->call('edit', $a->id)->set('parent_id', (string) $c->id)
        ->call('save')->assertHasErrors('parent_id');

    // não pode ser pai de si mesma
    Livewire::test(Index::class)->call('edit', $c->id)->set('parent_id', (string) $c->id)
        ->call('save')->assertHasErrors('parent_id');
});

it('rejeita ícone fora da lista', function () {
    Livewire::test(Index::class)->call('create')->set('name', 'X')->set('icon', 'nao-existe')
        ->call('save')->assertHasErrors('icon');
});

it('exclui categoria com subcategorias', function () {
    $a = Category::create(['type' => 'expense', 'name' => 'A', 'icon' => 'tag', 'color' => '#059669']);
    Category::create(['type' => 'expense', 'name' => 'B', 'icon' => 'tag', 'color' => '#059669', 'parent_id' => $a->id]);

    Livewire::test(Index::class)->call('edit', $a->id)->call('delete');

    expect(Category::count())->toBe(0);
});

it('exibe a tela', function () {
    Category::create(['type' => 'expense', 'name' => 'Mercadinho', 'icon' => 'shopping-cart', 'color' => '#ea580c']);
    $this->get('/categorias')->assertOk()->assertSee('Mercadinho');
});

it('ignora aba inválida vinda do navegador', function () {
    Livewire::test(Index::class)->set('tab', 'xyz')->assertSet('tab', 'expense')
        ->call('create')->set('name', 'Teste')->call('save')->assertHasNoErrors();

    expect(Category::where('name', 'Teste')->value('type'))->toBe('expense');
});
