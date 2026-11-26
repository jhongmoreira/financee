<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro do histórico de atividades. Gravado só pelo App\Services\ActivityLogger.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string|null $user_email
 * @property string $event
 * @property string $description
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property string|null $url
 * @property string|null $ip
 * @property string|null $user_agent
 * @property array<string, mixed>|null $meta
 * @property CarbonImmutable $created_at
 * @property-read User|null $user
 */
class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    /** Grupos para o filtro da tela: [rótulo, eventos]. */
    public const GROUPS = [
        'acesso' => ['Acessos', ['login', 'login_auto', 'app_opened', 'logout', 'login_failed', 'blocked', 'password_reset', 'registered']],
        'navegacao' => ['Navegação', ['page_view']],
        'alteracoes' => ['Criação, edição e exclusão', ['created', 'updated', 'deleted', 'profile_updated']],
        'admin' => ['Ações do administrador', ['admin']],
    ];

    protected $fillable = ['user_id', 'user_email', 'event', 'description', 'subject_type', 'subject_id', 'url', 'ip', 'user_agent', 'meta', 'created_at'];

    protected function casts(): array
    {
        return ['meta' => 'array', 'created_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @param Builder<ActivityLog> $query */
    public function scopeInGroup(Builder $query, string $group): void
    {
        if (isset(self::GROUPS[$group])) {
            $query->whereIn('event', self::GROUPS[$group][1]);
        }
    }

    /**
     * Ícone (Heroicons) e cor de cada tipo de evento.
     *
     * @return array{0: string, 1: string}
     */
    public function style(): array
    {
        return match ($this->event) {
            'login', 'login_auto' => ['arrow-right-end-on-rectangle', 'text-emerald-600 dark:text-emerald-400'],
            'app_opened' => ['device-phone-mobile', 'text-emerald-600 dark:text-emerald-400'],
            'logout' => ['arrow-left-start-on-rectangle', 'text-muted'],
            'login_failed', 'blocked' => ['shield-exclamation', 'text-rose-600 dark:text-rose-400'],
            'page_view' => ['eye', 'text-muted'],
            'created' => ['plus-circle', 'text-sky-600 dark:text-sky-400'],
            'updated', 'profile_updated' => ['pencil-square', 'text-amber-600 dark:text-amber-400'],
            'deleted' => ['trash', 'text-rose-600 dark:text-rose-400'],
            'admin' => ['shield-check', 'text-violet-600 dark:text-violet-400'],
            default => ['information-circle', 'text-muted'],
        };
    }

    /** "iPhone · Safari", "Windows · Chrome"... */
    public function device(): string
    {
        $ua = (string) $this->user_agent;
        if ($ua === '') {
            return '';
        }
        $os = match (true) {
            str_contains($ua, 'iPhone') => 'iPhone',
            str_contains($ua, 'iPad') => 'iPad',
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Mac OS') => 'Mac',
            str_contains($ua, 'Linux') => 'Linux',
            default => 'Outro',
        };
        $browser = match (true) {
            str_contains($ua, 'Edg/') => 'Edge',
            str_contains($ua, 'CriOS') || str_contains($ua, 'Chrome/') => 'Chrome',
            str_contains($ua, 'FxiOS') || str_contains($ua, 'Firefox/') => 'Firefox',
            str_contains($ua, 'Safari/') => 'Safari',
            default => '',
        };

        return trim($os.($browser ? ' · '.$browser : ''));
    }
}
