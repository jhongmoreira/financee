<?php

namespace App\Models;

use App\Casts\DateOnly;
use App\Models\Concerns\BelongsToUser;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $total_amount
 * @property int $installments
 * @property CarbonImmutable $first_date
 */
class InstallmentPlan extends Model
{
    use BelongsToUser;

    protected $fillable = ['description', 'total_amount', 'installments', 'first_date', 'credit_card_id', 'account_id', 'category_id'];

    protected function casts(): array
    {
        return ['total_amount' => 'integer', 'installments' => 'integer', 'first_date' => DateOnly::class];
    }

    /** @return HasMany<Transaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class)->orderBy('installment_number');
    }

    /**
     * Divide um total em N parcelas em centavos; o resto vai para a 1ª.
     * Ex.: 10000 em 3 → [3334, 3333, 3333]
     *
     * @return list<int>
     */
    public static function split(int $total, int $count): array
    {
        $base = intdiv($total, $count);
        $parts = array_fill(0, $count, $base);
        $parts[0] += $total - $base * $count;

        return $parts;
    }
}
