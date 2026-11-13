<?php

use App\Models\User;

it('exibe as páginas principais para usuário logado', function (string $url, string $text) {
    $this->actingAs(User::factory()->create(['name' => 'João Moreira']))
        ->get($url)->assertOk()->assertSee($text);
})->with([
    ['/', 'João'],
    ['/lancamentos', 'Lançamentos'],
    ['/planejamento', 'Planejamento'],
    ['/mais', 'Aparência'],
    ['/perfil', 'Seus dados'],
]);

it('aplica o tema salvo do usuário no html', function () {
    $user = User::factory()->create(['settings' => ['theme' => 'dark']]);

    $this->actingAs($user)->get('/')->assertSee('window.applyTheme("dark")', false);
});

it('serve o service worker com a versão e a página offline', function () {
    $this->get('/sw.js')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/javascript; charset=utf-8')
        ->assertSee("const VERSION = 'dev'", false)
        ->assertSee('"/offline"', false);

    $this->get('/offline')->assertOk()->assertSee('sem internet');
});

it('publica o manifest do PWA', function () {
    $manifest = json_decode(file_get_contents(public_path('manifest.webmanifest')), true);

    expect($manifest)->toMatchArray(['name' => 'Financee', 'display' => 'standalone', 'start_url' => '/'])
        ->and(collect($manifest['icons'])->pluck('src')->all())
        ->each(fn ($src) => $src->toBeString()->and(file_exists(public_path(ltrim($src->value, '/'))))->toBeTrue());
});
