<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $current_value
 * @property CarbonImmutable|null $current_value_at
 * @property CarbonImmutable|null $maturity_date
 * @property-read InvestmentPortfolio|null $portfolio
 */
class InvestmentAsset extends Model
{
    use BelongsToUser;

    /** classe => [rótulo, cor] */
    public const CLASSES = [
        'stock' => ['Ações', '#2563eb'],
        'fii' => ['Fundos imobiliários', '#0d9488'],
        'etf' => ['ETFs', '#4f46e5'],
        'cdb' => ['CDB', '#059669'],
        'lci_lca' => ['LCI / LCA', '#65a30d'],
        'treasury' => ['Tesouro Direto', '#ca8a04'],
        'fixed_income' => ['Outros renda fixa', '#16a34a'],
        'fund' => ['Fundos de investimento', '#9333ea'],
        'pension' => ['Previdência', '#db2777'],
        'crypto' => ['Criptomoedas', '#ea580c'],
        'other' => ['Outros', '#6b7280'],
    ];

    protected $fillable = ['portfolio_id', 'name', 'ticker', 'class', 'rate_description', 'maturity_date', 'quantity', 'current_value', 'current_value_at', 'archived_at'];

    protected function casts(): array
    {
        return [
            'current_value' => 'integer',
            'current_value_at' => 'immutable_date',
            'maturity_date' => 'immutable_date',
            'quantity' => 'decimal:8',
            'archived_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<InvestmentPortfolio, $this> */
    public function portfolio(): BelongsTo
    {
        return $this->belongsTo(InvestmentPortfolio::class, 'portfolio_id');
    }

    /** @return HasMany<InvestmentMovement, $this> */
    public function movements(): HasMany
    {
        return $this->hasMany(InvestmentMovement::class, 'asset_id');
    }

    public function classLabel(): string
    {
        return self::CLASSES[$this->class][0] ?? 'Outros';
    }

    public function classColor(): string
    {
        return self::CLASSES[$this->class][1] ?? '#6b7280';
    }
}
