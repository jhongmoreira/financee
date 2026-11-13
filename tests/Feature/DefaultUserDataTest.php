<?php

use App\Models\Account;
use App\Models\Category;
use App\Models\User;
use App\Services\DefaultUserData;

it('cria categorias padrão e a conta Carteira ao se cadastrar', function () {
    $this->post('/register', [
        'name' => 'Ana', 'email' => 'ana@example.com',
        'password' => 'Senha-forte-123', 'password_confirmation' => 'Senha-forte-123',
    ]);

    $user = User::where('email', 'ana@example.com')->firstOrFail();
    $categories = Category::withoutGlobalScopes()->where('user_id', $user->id);

    expect((clone $categories)->where('type', 'expense')->whereNull('parent_id')->count())->toBe(count(DefaultUserData::EXPENSES))
        ->and((clone $categories)->where('type', 'income')->count())->toBe(count(DefaultUserData::INCOMES))
        ->and((clone $categories)->where('name', 'Mercado')->value('parent_id'))->not->toBeNull()
        ->and(Account::withoutGlobalScopes()->where('user_id', $user->id)->pluck('name')->all())->toBe(['Carteira']);
});

it('é idempotente', function () {
    $user = User::factory()->create();
    $service = app(DefaultUserData::class);

    $service->createFor($user);
    $count = Category::withoutGlobalScopes()->where('user_id', $user->id)->count();
    $service->createFor($user);

    expect(Category::withoutGlobalScopes()->where('user_id', $user->id)->count())->toBe($count)
        ->and(Account::withoutGlobalScopes()->where('user_id', $user->id)->count())->toBe(1);
});

it('isola os dados entre usuários', function () {
    [$ana, $beto] = User::factory()->count(2)->create();
    app(DefaultUserData::class)->createFor($ana);
    app(DefaultUserData::class)->createFor($beto);

    $this->actingAs($ana);
    expect(Account::count())->toBe(1)
        ->and(Account::first()->user_id)->toBe($ana->id)
        ->and(Category::pluck('user_id')->unique()->all())->toBe([$ana->id]);
});

it('apaga os dados junto com o usuário', function () {
    $user = User::factory()->create();
    app(DefaultUserData::class)->createFor($user);

    $user->delete();

    expect(Category::withoutGlobalScopes()->where('user_id', $user->id)->count())->toBe(0)
        ->and(Account::withoutGlobalScopes()->where('user_id', $user->id)->count())->toBe(0);
});
