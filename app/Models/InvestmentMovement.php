<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use App\Services\AccountBalanceService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Aporte, resgate, rendimento ou atualização de valor de um ativo.
 * Com account_id: aporte sai da conta, resgate/rendimento entram na conta
 * (como movimentação, NÃO como despesa/receita).
 *
 * @property int $amount
 * @property CarbonImmutable $date
 * @property-read Account|null $account
 * @property-read InvestmentAsset|null $asset
 */
class InvestmentMovement extends Model
{
    use BelongsToUser;

    public const TYPES = [
        'contribution' => ['Aporte', 'arrow-down-tray', '#2563eb'],
        'withdrawal' => ['Resgate', 'arrow-up-tray', '#ea580c'],
        'income' => ['Rendimento', 'sparkles', '#059669'],
        'valuation' => ['Atualização de valor', 'arrow-path', '#6b7280'],
    ];

    protected $fillable = ['asset_id', 'type', 'amount', 'quantity', 'date', 'account_id', 'notes'];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'date' => 'immutable_date', 'quantity' => 'decimal:8'];
    }

    protected static function booted(): void
    {
        $flush = fn () => app(AccountBalanceService::class)->flush();
        static::saved($flush);
        static::deleted($flush);
    }

    /** @return BelongsTo<InvestmentAsset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(InvestmentAsset::class, 'asset_id');
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type][0] ?? $this->type;
    }

    /** Efeito na conta vinculada: aporte sai (−), resgate e rendimento entram (+). */
    public function accountEffect(): int
    {
        if (! $this->account_id) {
            return 0;
        }

        return match ($this->type) {
            'contribution' => -$this->amount,
            'withdrawal', 'income' => $this->amount,
            default => 0,
        };
    }
}
