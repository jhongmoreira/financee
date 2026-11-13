<?php

namespace App\Livewire;

use App\Actions\Fortify\PasswordValidationRules;
use App\Models\User;
use App\Services\UserDataReset;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Intervention\Image\ImageManager;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

#[Title('Perfil')]
class Profile extends Component
{
    use PasswordValidationRules;
    use WithFileUploads;

    public string $name = '';

    public string $email = '';

    #[Validate('image|max:8192')]
    public ?TemporaryUploadedFile $photo = null;

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public string $delete_password = '';

    public string $reset_password = '';

    public function mount(): void
    {
        $this->name = $this->user()->name;
        $this->email = $this->user()->email;
    }

    public function saveProfile(): void
    {
        $user = $this->user();

        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)->ignore($user->id)],
        ]);

        $user->update($data);
        $this->dispatch('toast', message: 'Perfil atualizado.');
    }

    /** Salva a foto assim que o arquivo termina de subir. */
    public function updatedPhoto(): void
    {
        $this->validateOnly('photo');

        $user = $this->user();
        $path = 'avatars/'.$user->id.'-'.Str::random(8).'.webp';

        $image = ImageManager::gd()->read($this->photo->getRealPath())->cover(256, 256)->toWebp(82);
        Storage::disk('public')->put($path, (string) $image);

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        $user->update(['avatar_path' => $path]);
        $this->photo->delete();
        $this->photo = null;
        $this->dispatch('toast', message: 'Foto atualizada.');
    }

    public function removePhoto(): void
    {
        $user = $this->user();

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
            $user->update(['avatar_path' => null]);
        }

        $this->dispatch('toast', message: 'Foto removida.');
    }

    public function updatePassword(): void
    {
        $this->validate([
            'current_password' => ['required', 'string', 'current_password:web'],
            'password' => $this->passwordRules(),
        ]);

        $this->user()->update(['password' => $this->password]);
        $this->reset('current_password', 'password', 'password_confirmation');
        $this->dispatch('close-sheet', 'password');
        $this->dispatch('toast', message: 'Senha alterada.');
    }

    /** Apaga todos os dados financeiros e recria a estrutura inicial (mantém o login). */
    public function resetData(UserDataReset $reset): void
    {
        $this->validate(['reset_password' => ['required', 'string', 'current_password:web']], attributes: ['reset_password' => 'senha']);

        $reset->reset($this->user());

        $this->reset('reset_password');
        session()->flash('toast', 'Pronto! Seus dados foram apagados e você está começando do zero.');
        $this->redirectRoute('home', navigate: true);
    }

    public function deleteAccount(): void
    {
        $this->validate(['delete_password' => ['required', 'string', 'current_password:web']]);

        $user = $this->user();

        Auth::guard('web')->logout();

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        $user->delete();
        session()->invalidate();
        session()->regenerateToken();

        $this->redirectRoute('login');
    }

    public function render(): View
    {
        return view('livewire.profile', ['user' => $this->user()]);
    }

    private function user(): User
    {
        /** @var User */
        return Auth::user();
    }
}
