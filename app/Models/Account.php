<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use App\Services\AccountBalanceService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $initial_balance centavos
 */
class Account extends Model
{
    use BelongsToUser;

    public const TYPES = [
        'checking' => ['Conta corrente', 'building-library'],
        'savings' => ['Poupança', 'banknotes'],
        'wallet' => ['Carteira (dinheiro)', 'wallet'],
        'other' => ['Outra', 'square-3-stack-3d'],
    ];

    protected $fillable = [
        'institution_id', 'name', 'type', 'initial_balance', 'color', 'include_in_total', 'sort_order', 'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'initial_balance' => 'integer',
            'include_in_total' => 'boolean',
            'archived_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Institution, $this> */
    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    /** @param Builder<Account> $query */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('archived_at');
    }

    /** @param Builder<Account> $query */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('name');
    }

    /** Saldo atual em centavos (saldo inicial + lançamentos efetivados). */
    public function balance(): int
    {
        return app(AccountBalanceService::class)->current()[$this->id] ?? $this->initial_balance;
    }

    /** @return HasMany<Transaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /** Tem lançamentos ou transferências? (nesse caso só pode ser arquivada) */
    public function hasMovements(): bool
    {
        return $this->transactions()->exists()
            || InvoicePayment::query()->where('account_id', $this->id)->exists()
            || Transfer::query()->where(fn ($q) => $q->where('from_account_id', $this->id)->orWhere('to_account_id', $this->id))->exists();
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type][0] ?? 'Conta';
    }

    public function typeIcon(): string
    {
        return self::TYPES[$this->type][1] ?? 'building-library';
    }
}
