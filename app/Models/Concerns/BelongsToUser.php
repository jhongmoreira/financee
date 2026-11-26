<?php

namespace App\Models\Concerns;

use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Isola os dados por usuário: com alguém logado, toda consulta é filtrada
 * pelo user_id dele e todo registro novo recebe o user_id automaticamente.
 */
trait BelongsToUser
{
    public static function bootBelongsToUser(): void
    {
        static::addGlobalScope('user', function (Builder $query) {
            if (Auth::check()) {
                $query->where($query->qualifyColumn('user_id'), Auth::id());
            }
        });

        static::creating(function ($model) {
            if (! $model->user_id && Auth::check()) {
                $model->user_id = Auth::id();
            }
        });

        // Histórico de atividades (só ações de quem está logado; o cron não entra)
        foreach (['created', 'updated', 'deleted'] as $event) {
            static::$event(fn ($model) => app(ActivityLogger::class)->model($model, $event));
        }
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
