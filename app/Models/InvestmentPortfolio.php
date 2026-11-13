<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Carteira/corretora de investimentos. Saldo SEPARADO do patrimônio corrente.
 *
 * @property-read Institution|null $institution
 */
class InvestmentPortfolio extends Model
{
    use BelongsToUser;

    protected $fillable = ['institution_id', 'name', 'color', 'archived_at'];

    protected function casts(): array
    {
        return ['archived_at' => 'datetime'];
    }

    /** @return BelongsTo<Institution, $this> */
    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    /** @return HasMany<InvestmentAsset, $this> */
    public function assets(): HasMany
    {
        return $this->hasMany(InvestmentAsset::class, 'portfolio_id')->orderBy('name');
    }

    /** @param Builder<InvestmentPortfolio> $query */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('archived_at');
    }
}
