<?php

use App\Livewire\More;
use App\Livewire\Profile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create(['password' => 'senha-atual-123']);
    $this->actingAs($this->user);
});

it('atualiza nome e e-mail', function () {
    Livewire::test(Profile::class)
        ->set('name', 'Novo Nome')
        ->set('email', 'novo@example.com')
        ->call('saveProfile')
        ->assertHasNoErrors()
        ->assertDispatched('toast');

    expect($this->user->fresh())->name->toBe('Novo Nome')->email->toBe('novo@example.com');
});

it('não permite e-mail de outro usuário', function () {
    $other = User::factory()->create();

    Livewire::test(Profile::class)->set('email', $other->email)->call('saveProfile')->assertHasErrors('email');
});

it('altera a senha exigindo a senha atual', function () {
    Livewire::test(Profile::class)
        ->set('current_password', 'errada')
        ->set('password', 'Nova-senha-456')
        ->set('password_confirmation', 'Nova-senha-456')
        ->call('updatePassword')
        ->assertHasErrors('current_password');

    Livewire::test(Profile::class)
        ->set('current_password', 'senha-atual-123')
        ->set('password', 'Nova-senha-456')
        ->set('password_confirmation', 'Nova-senha-456')
        ->call('updatePassword')
        ->assertHasNoErrors();

    expect(Hash::check('Nova-senha-456', $this->user->fresh()->password))->toBeTrue();
});

it('salva a foto redimensionada em webp e remove a anterior', function () {
    Storage::fake('public');

    Livewire::test(Profile::class)->set('photo', UploadedFile::fake()->image('eu.jpg', 1200, 800));
    $first = $this->user->fresh()->avatar_path;

    expect($first)->toEndWith('.webp');
    Storage::disk('public')->assertExists($first);
    [$width, $height, $type] = getimagesizefromstring(Storage::disk('public')->get($first));
    expect([$width, $height, $type])->toBe([256, 256, IMAGETYPE_WEBP]);

    Livewire::test(Profile::class)->set('photo', UploadedFile::fake()->image('eu2.png', 300, 300));
    Storage::disk('public')->assertMissing($first);

    Livewire::test(Profile::class)->call('removePhoto');
    expect($this->user->fresh()->avatar_path)->toBeNull();
});

it('rejeita arquivo que não é imagem', function () {
    Storage::fake('public');

    Livewire::test(Profile::class)
        ->set('photo', UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'))
        ->assertHasErrors('photo');
});

it('exclui a conta com a senha correta', function () {
    Livewire::test(Profile::class)->set('delete_password', 'errada')->call('deleteAccount')->assertHasErrors('delete_password');
    expect(User::find($this->user->id))->not->toBeNull();

    Livewire::test(Profile::class)->set('delete_password', 'senha-atual-123')->call('deleteAccount')->assertRedirect('/login');
    expect(User::find($this->user->id))->toBeNull();
    $this->assertGuest();
});

it('salva a preferência de tema', function () {
    Livewire::test(More::class)->set('theme', 'dark')->assertHasNoErrors();
    expect($this->user->fresh()->theme())->toBe('dark');

    Livewire::test(More::class)->set('theme', 'roxo')->assertHasErrors('theme');
    expect($this->user->fresh()->theme())->toBe('dark');
});
