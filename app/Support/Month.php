<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/** Mês de referência no formato "YYYY-MM" (usado na URL e na navegação). */
final class Month
{
    public static function parse(?string $value): CarbonImmutable
    {
        if ($value && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value)) {
            return CarbonImmutable::createFromFormat('!Y-m', $value)->startOfMonth();
        }

        return CarbonImmutable::today()->startOfMonth();
    }

    public static function key(CarbonImmutable $month): string
    {
        return $month->format('Y-m');
    }

    public static function label(CarbonImmutable $month): string
    {
        $label = Str::ucfirst($month->locale('pt_BR')->translatedFormat('F'));

        return $month->year === CarbonImmutable::today()->year ? $label : $label.' '.$month->year;
    }

    public static function dayLabel(CarbonImmutable $date): string
    {
        return match (true) {
            $date->isToday() => 'Hoje',
            $date->isYesterday() => 'Ontem',
            $date->isTomorrow() => 'Amanhã',
            default => Str::ucfirst($date->locale('pt_BR')->translatedFormat('l, j \d\e F')),
        };
    }
}
