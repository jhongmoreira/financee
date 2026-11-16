<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Categoria de conta (ex.: "Reserva de emergência"): agrupa contas para
 * filtrar o saldo da tela inicial. Não confundir com Category (lançamentos).
 */
class AccountCategory extends Model
{
    use BelongsToUser;

    protected $fillable = ['name'];

    /** @return HasMany<Account, $this> */
    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }
}
