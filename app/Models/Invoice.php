<?php

namespace App\Models;

use App\Casts\DateOnly;
use App\Models\Concerns\BelongsToUser;
use App\Support\Month;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property CarbonImmutable $closing_date
 * @property CarbonImmutable $due_date
 * @property int $carried_in
 * @property int $carried_out
 * @property-read CreditCard|null $creditCard
 */
class Invoice extends Model
{
    use BelongsToUser;

    protected $fillable = ['credit_card_id', 'reference_month', 'closing_date', 'due_date', 'carried_in', 'carried_out'];

    protected function casts(): array
    {
        return [
            'closing_date' => DateOnly::class,
            'due_date' => DateOnly::class,
            'carried_in' => 'integer',
            'carried_out' => 'integer',
        ];
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

    /** @return HasMany<InvoicePayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class)->orderBy('paid_at');
    }

    public function month(): CarbonImmutable
    {
        return Month::parse($this->reference_month);
    }

    /** Ex.: "Dez/26" */
    public function shortLabel(): string
    {
        return ucfirst($this->month()->locale('pt_BR')->translatedFormat('M')).'/'.$this->month()->format('y');
    }
}
