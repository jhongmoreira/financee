<?php

namespace App\Support;

/**
 * Valores monetários são sempre inteiros em centavos.
 */
final class Money
{
    public static function format(int $cents, bool $withSymbol = true, bool $signed = false): string
    {
        $sign = $cents < 0 ? '-' : ($signed && $cents > 0 ? '+' : '');
        $value = number_format(abs($cents) / 100, 2, ',', '.');

        return $sign.($withSymbol ? 'R$ ' : '').$value;
    }

    /** Converte "1.234,56", "1234.56" ou "1234" em centavos. */
    public static function parse(string|int|float|null $input): int
    {
        if ($input === null || $input === '') {
            return 0;
        }

        if (is_int($input)) {
            return $input * 100;
        }

        if (is_float($input)) {
            return (int) round($input * 100);
        }

        $clean = preg_replace('/[^\d,.\-]/', '', $input) ?? '';

        if (str_contains($clean, ',')) {
            $clean = str_replace(['.', ','], ['', '.'], $clean);
        }

        return (int) round(((float) $clean) * 100);
    }
}
