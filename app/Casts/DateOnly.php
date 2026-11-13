<?php

namespace App\Casts;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Data sem horário, gravada como "YYYY-MM-DD".
 *
 * O cast "date" do Laravel grava "YYYY-MM-DD 00:00:00"; no SQLite as datas são
 * texto, e comparações como `date <= '2026-11-30'` deixariam de fora o próprio
 * dia 30. Com este cast, gravação e consultas usam sempre o mesmo formato.
 *
 * @implements CastsAttributes<CarbonImmutable, mixed>
 */
class DateOnly implements CastsAttributes
{
    /** Sempre reler do valor gravado (não reaproveitar o objeto DateTime recebido). */
    public bool $withoutObjectCaching = true;

    public function get(Model $model, string $key, mixed $value, array $attributes): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        return CarbonImmutable::createFromFormat('!Y-m-d', substr((string) $value, 0, 10));
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return CarbonImmutable::parse((string) $value)->format('Y-m-d');
    }
}
