<?php

namespace App\Models;

use App\Casts\DateOnly;
use App\Models\Concerns\BelongsToUser;
use App\Services\AccountBalanceService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $amount centavos
 * @property CarbonImmutable $date
 * @property CarbonImmutable|null $paid_at
 * @property-read Account|null $fromAccount
 * @property-read Account|null $toAccount
 */
class Transfer extends Model
{
    use BelongsToUser;

    protected $fillable = ['from_account_id', 'to_account_id', 'amount', 'date', 'paid_at', 'description'];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'date' => DateOnly::class,
            'paid_at' => DateOnly::class,
        ];
    }

    protected static function booted(): void
    {
        $flush = fn () => app(AccountBalanceService::class)->flush();
        static::saved($flush);
        static::deleted($flush);
    }

    /** @return BelongsTo<Account, $this> */
    public function fromAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'from_account_id');
    }

    /** @return BelongsTo<Account, $this> */
    public function toAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'to_account_id');
    }

    public function isPaid(): bool
    {
        return $this->paid_at !== null;
    }
}
