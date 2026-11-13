<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use NotificationChannels\WebPush\HasPushSubscriptions;

/**
 * @property array<string, mixed>|null $settings
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasPushSubscriptions, Notifiable;

    /** Preferências padrão do usuário (coluna JSON "settings"). */
    public const DEFAULT_SETTINGS = [
        'theme' => 'system',          // system | light | dark
        'month_start_day' => 1,       // dia de início do mês financeiro
    ];

    /** Tipos de aviso e se vêm ligados por padrão. */
    public const NOTIFICATION_TYPES = [
        'due' => ['Contas a vencer e vencidas', 'Com antecedência, no dia do vencimento e no dia seguinte se continuar pendente.'],
        'invoices' => ['Faturas de cartão', 'Quando a fatura fecha e quando está perto de vencer.'],
        'budget' => ['Orçamentos', 'Ao atingir 80% e 100% do orçamento de uma categoria.'],
        'forecast' => ['Saldo previsto negativo', 'Se a previsão dos próximos 3 meses ficar negativa (no máximo 1 vez por semana).'],
        'investments' => ['Lembrete de investimentos', 'No dia 1º, para atualizar o valor dos seus investimentos.'],
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

    public function wantsNotification(string $type): bool
    {
        return (bool) Arr::get($this->settings ?? [], 'notifications.'.$type, true);
    }

    /** Quantos dias antes avisar sobre contas a vencer (0 = só no dia). */
    public function dueDaysBefore(): int
    {
        return max(0, min(7, (int) Arr::get($this->settings ?? [], 'notifications.due_days', 1)));
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
