<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Institution extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    /** @return array<int, string> id => nome (bancos primeiro, "Outra" por último) */
    public static function options(?string $type = null): array
    {
        return static::query()
            ->when($type, fn (Builder $q) => $q->whereIn('type', [$type, 'other']))
            ->orderByRaw("CASE type WHEN 'other' THEN 2 WHEN 'broker' THEN 1 ELSE 0 END")
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}
