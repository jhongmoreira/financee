<?php

use App\Models\User;

it('redireciona visitante para o login', function () {
    $this->get('/')->assertRedirect('/login');
});

it('exibe as telas de autenticação', function (string $url, string $text) {
    $this->get($url)->assertOk()->assertSee($text);
})->with([
    ['/login', 'Entrar'],
    ['/register', 'Criar conta'],
    ['/forgot-password', 'Enviar link'],
]);

it('faz login com credenciais válidas', function () {
    $user = User::factory()->create(['password' => 'segredo123']);

    $this->post('/login', ['email' => $user->email, 'password' => 'segredo123'])
        ->assertRedirect('/');

    $this->assertAuthenticatedAs($user);
});

it('recusa senha errada', function () {
    $user = User::factory()->create(['password' => 'segredo123']);

    $this->post('/login', ['email' => $user->email, 'password' => 'errada'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('cadastra um novo usuário', function () {
    $this->post('/register', [
        'name' => 'Maria Silva',
        'email' => 'maria@example.com',
        'password' => 'Senha-forte-123',
        'password_confirmation' => 'Senha-forte-123',
    ])->assertRedirect('/');

    $this->assertAuthenticated();
    expect(User::where('email', 'maria@example.com')->exists())->toBeTrue();
});

it('faz logout', function () {
    $this->actingAs(User::factory()->create())->post('/logout')->assertRedirect('/');
    $this->assertGuest();
});
