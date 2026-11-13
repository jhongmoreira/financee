<?php

namespace App\Livewire;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Mais')]
class More extends Component
{
    public string $theme = 'system';

    public function mount(): void
    {
        $this->theme = $this->user()->theme();
    }

    public function updatedTheme(string $value): void
    {
        $this->validate(['theme' => ['required', Rule::in(User::THEMES)]]);
        $this->user()->updateSetting('theme', $value);
    }

    public function render(): View
    {
        return view('livewire.more', ['user' => $this->user()]);
    }

    private function user(): User
    {
        /** @var User */
        return Auth::user();
    }
}
