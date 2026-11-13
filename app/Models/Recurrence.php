<?php

namespace App\Models;

use App\Casts\DateOnly;
use App\Models\Concerns\BelongsToUser;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Lançamento fixo (salário, aluguel, assinatura...). Gera lançamentos previstos
 * com antecedência (ver RecurrenceService).
 *
 * @property int $amount
 * @property CarbonImmutable $start_date
 * @property CarbonImmutable|null $end_date
 * @property CarbonImmutable|null $generated_until
 * @property-read Category|null $category
 * @property-read Account|null $account
 * @property-read CreditCard|null $creditCard
 */
class Recurrence extends Model
{
    use BelongsToUser;

    /** frequência => [rótulo, meses entre ocorrências (0 = semanal)] */
    public const FREQUENCIES = [
        'weekly' => ['Semanal', 0],
        'monthly' => ['Mensal', 1],
        'bimonthly' => ['Bimestral', 2],
        'quarterly' => ['Trimestral', 3],
        'semiannual' => ['Semestral', 6],
        'yearly' => ['Anual', 12],
    ];

    protected $fillable = [
        'type', 'description', 'amount', 'category_id', 'account_id', 'credit_card_id',
        'frequency', 'start_date', 'end_date', 'auto_mark_paid', 'generated_until',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'start_date' => DateOnly::class,
            'end_date' => DateOnly::class,
            'generated_until' => DateOnly::class,
            'auto_mark_paid' => 'boolean',
        ];
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /** @return BelongsTo<CreditCard, $this> */
    public function creditCard(): BelongsTo
    {
        return $this->belongsTo(CreditCard::class);
    }

    /** @return HasMany<Transaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /** @param Builder<Recurrence> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', today()->toDateString()));
    }

    public function frequencyLabel(): string
    {
        return self::FREQUENCIES[$this->frequency][0] ?? 'Mensal';
    }

    /** Valor equivalente por mês (para "total comprometido por mês"). */
    public function monthlyAmount(): int
    {
        $months = self::FREQUENCIES[$this->frequency][1] ?? 1;

        return $months === 0 ? (int) round($this->amount * 52 / 12) : intdiv($this->amount, $months);
    }

    /** n-ésima ocorrência (0 = data inicial), sem "escorregar" o dia ao longo dos meses. */
    public function occurrence(int $n): CarbonImmutable
    {
        $months = self::FREQUENCIES[$this->frequency][1] ?? 1;

        return $months === 0
            ? $this->start_date->addWeeks($n)
            : $this->start_date->addMonthsNoOverflow($n * $months);
    }
}
