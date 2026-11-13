<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use App\Services\AccountBalanceService;
use Carbon\CarbonImmutable;
use Database\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $amount centavos (sempre positivo)
 * @property CarbonImmutable $date
 * @property CarbonImmutable|null $paid_at
 * @property-read Account|null $account
 * @property-read Category|null $category
 * @property-read CreditCard|null $creditCard
 * @property-read Invoice|null $invoice
 */
class Transaction extends Model
{
    use BelongsToUser;

    /** @use HasFactory<TransactionFactory> */
    use HasFactory;

    public const TYPES = ['expense' => 'Despesa', 'income' => 'Receita'];

    protected $fillable = [
        'type', 'description', 'notes', 'amount', 'date', 'paid_at', 'account_id', 'category_id', 'is_adjustment',
        'credit_card_id', 'invoice_id', 'installment_plan_id', 'installment_number', 'installment_total',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'date' => 'immutable_date',
            'paid_at' => 'immutable_date',
            'is_adjustment' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        $flush = fn () => app(AccountBalanceService::class)->flush();
        static::saved($flush);
        static::deleted($flush);
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsTo<CreditCard, $this> */
    public function creditCard(): BelongsTo
    {
        return $this->belongsTo(CreditCard::class);
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** @return BelongsTo<InstallmentPlan, $this> */
    public function installmentPlan(): BelongsTo
    {
        return $this->belongsTo(InstallmentPlan::class);
    }

    public function isCard(): bool
    {
        return $this->credit_card_id !== null;
    }

    /** Ex.: "3/10" */
    public function installmentLabel(): ?string
    {
        return $this->installment_total ? $this->installment_number.'/'.$this->installment_total : null;
    }

    /** @param Builder<Transaction> $query */
    public function scopeInMonth(Builder $query, CarbonImmutable $month): void
    {
        $query->whereBetween('date', [$month->startOfMonth()->toDateString(), $month->endOfMonth()->toDateString()]);
    }

    /** @param Builder<Transaction> $query */
    public function scopeWithoutAdjustments(Builder $query): void
    {
        $query->where('is_adjustment', false);
    }

    public function isPaid(): bool
    {
        return $this->paid_at !== null;
    }

    public function isOverdue(): bool
    {
        return ! $this->isPaid() && $this->date->isBefore(today());
    }

    /** Valor com sinal: receita positiva, despesa negativa. */
    public function signedAmount(): int
    {
        return $this->type === 'income' ? $this->amount : -$this->amount;
    }
}
