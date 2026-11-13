<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $limit centavos
 */
class CreditCard extends Model
{
    use BelongsToUser;

    public const BRANDS = [
        'mastercard' => 'Mastercard',
        'visa' => 'Visa',
        'elo' => 'Elo',
        'amex' => 'American Express',
        'hipercard' => 'Hipercard',
        'other' => 'Outra',
    ];

    protected $fillable = [
        'institution_id', 'payment_account_id', 'name', 'brand', 'limit', 'closing_day', 'due_day', 'color', 'sort_order', 'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'limit' => 'integer',
            'closing_day' => 'integer',
            'due_day' => 'integer',
            'archived_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Institution, $this> */
    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    /** @return BelongsTo<Account, $this> */
    public function paymentAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'payment_account_id');
    }

    /** @param Builder<CreditCard> $query */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('archived_at');
    }

    /** @param Builder<CreditCard> $query */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('name');
    }

    /** @return HasMany<Invoice, $this> */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /** @return HasMany<Transaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /** Limite utilizado = compras − estornos − pagamentos (inclui parcelas futuras). */
    public function usedLimit(): int
    {
        $purchases = (int) $this->transactions()
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE -amount END), 0) AS total")
            ->value('total');

        $payments = (int) InvoicePayment::query()->whereIn('invoice_id', $this->invoices()->select('id'))->sum('amount');

        return max(0, $purchases - $payments);
    }

    public function availableLimit(): int
    {
        return $this->limit - $this->usedLimit();
    }

    public function brandLabel(): string
    {
        return self::BRANDS[$this->brand] ?? 'Cartão';
    }
}
