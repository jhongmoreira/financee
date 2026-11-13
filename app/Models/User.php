<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @property array<string, mixed>|null $settings
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** Preferências padrão do usuário (coluna JSON "settings"). */
    public const DEFAULT_SETTINGS = [
        'theme' => 'system',          // system | light | dark
        'month_start_day' => 1,       // dia de início do mês financeiro
    ];

    public const THEMES = ['system', 'light', 'dark'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'avatar_path' => null,
        'settings' => null,
    ];

    protected $fillable = [
        'name',
        'email',
        'password',
        'avatar_path',
        'settings',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'settings' => 'array',
        ];
    }

    public function setting(string $key): mixed
    {
        return Arr::get($this->settings ?? [], $key, self::DEFAULT_SETTINGS[$key] ?? null);
    }

    public function updateSetting(string $key, mixed $value): void
    {
        $settings = $this->settings ?? [];
        Arr::set($settings, $key, $value);
        $this->update(['settings' => $settings]);
    }

    public function theme(): string
    {
        $theme = $this->setting('theme');

        return in_array($theme, self::THEMES, true) ? $theme : 'system';
    }

    public function avatarUrl(): ?string
    {
        return $this->avatar_path ? Storage::disk('public')->url($this->avatar_path) : null;
    }

    public function initials(): string
    {
        return Str::of($this->name)->explode(' ')->filter()
            ->map(fn (string $part) => Str::upper(Str::substr($part, 0, 1)))
            ->take(2)->implode('');
    }

    public function firstName(): string
    {
        return Str::before($this->name, ' ') ?: $this->name;
    }
}
