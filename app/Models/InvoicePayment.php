<?php

namespace App\Models;

use App\Casts\DateOnly;
use App\Models\Concerns\BelongsToUser;
use App\Services\AccountBalanceService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $amount
 * @property int $carried_amount
 * @property CarbonImmutable $paid_at
 * @property-read Invoice|null $invoice
 * @property-read Account|null $account
 */
class InvoicePayment extends Model
{
    use BelongsToUser;

    protected $fillable = ['invoice_id', 'account_id', 'amount', 'paid_at', 'carried_amount'];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'carried_amount' => 'integer', 'paid_at' => DateOnly::class];
    }

    protected static function booted(): void
    {
        $flush = fn () => app(AccountBalanceService::class)->flush();
        static::saved($flush);
        static::deleted($flush);
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
