<?php

namespace App\Services;

use App\Models\InvestmentAsset;
use App\Models\InvestmentMovement;
use Illuminate\Support\Collection;

/**
 * Investimentos (controle manual). O valor atual de cada ativo é reconstruído a
 * partir dos movimentos, em ordem de data:
 *   aporte +, resgate −, rendimento reinvestido + (sem conta), atualização = define o valor.
 * Rentabilidade = valor atual + resgates + rendimentos recebidos em conta − aportes.
 */
class InvestmentService
{
    public function recalculate(InvestmentAsset $asset): void
    {
        $value = 0;
        $valueAt = null;

        foreach ($asset->movements()->orderBy('date')->orderBy('id')->get() as $m) {
            $value = match ($m->type) {
                'contribution' => $value + $m->amount,
                'withdrawal' => $value - $m->amount,
                'income' => $m->account_id ? $value : $value + $m->amount,
                'valuation' => $m->amount,
                default => $value,
            };
            $valueAt = $m->date;
        }

        $asset->update(['current_value' => max(0, $value), 'current_value_at' => $valueAt]);
    }

    /**
     * @param  Collection<int, InvestmentAsset>  $assets
     * @return array<int, array{contributed: int, withdrawn: int, cash_income: int, invested: int, current: int, gain: int, pct: float|null}>
     */
    public function stats(Collection $assets): array
    {
        if ($assets->isEmpty()) {
            return [];
        }

        $rows = InvestmentMovement::query()->whereIn('asset_id', $assets->pluck('id'))
            ->selectRaw("asset_id,
                COALESCE(SUM(CASE WHEN type = 'contribution' THEN amount END), 0) AS contributed,
                COALESCE(SUM(CASE WHEN type = 'withdrawal' THEN amount END), 0) AS withdrawn,
                COALESCE(SUM(CASE WHEN type = 'income' AND account_id IS NOT NULL THEN amount END), 0) AS cash_income")
            ->groupBy('asset_id')->get()->keyBy('asset_id');

        $result = [];
        foreach ($assets as $asset) {
            $row = $rows->get($asset->id);
            $contributed = (int) $row?->getAttribute('contributed');
            $withdrawn = (int) $row?->getAttribute('withdrawn');
            $cash = (int) $row?->getAttribute('cash_income');
            $gain = $asset->current_value + $withdrawn + $cash - $contributed;

            $result[$asset->id] = [
                'contributed' => $contributed,
                'withdrawn' => $withdrawn,
                'cash_income' => $cash,
                'invested' => $contributed - $withdrawn,
                'current' => $asset->current_value,
                'gain' => $gain,
                'pct' => $contributed > 0 ? round($gain / $contributed * 100, 2) : null,
            ];
        }

        return $result;
    }

    /**
     * Soma de várias estatísticas (carteira ou total).
     *
     * @param  array<int, array{contributed: int, withdrawn: int, cash_income: int, invested: int, current: int, gain: int, pct: float|null}>  $stats
     * @return array{contributed: int, invested: int, current: int, gain: int, pct: float|null}
     */
    public function sum(array $stats): array
    {
        $contributed = array_sum(array_column($stats, 'contributed'));
        $gain = array_sum(array_column($stats, 'gain'));

        return [
            'contributed' => $contributed,
            'invested' => array_sum(array_column($stats, 'invested')),
            'current' => array_sum(array_column($stats, 'current')),
            'gain' => $gain,
            'pct' => $contributed > 0 ? round($gain / $contributed * 100, 2) : null,
        ];
    }

    /** Valor total investido atual (ativos não arquivados), para o card da Início. */
    public function totalValue(): int
    {
        return (int) InvestmentAsset::query()->whereNull('archived_at')
            ->whereHas('portfolio', fn ($q) => $q->whereNull('archived_at'))->sum('current_value');
    }
}
