<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\Transfer;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;

/**
 * Saldos das contas calculados com poucas consultas agregadas:
 *   saldo = saldo inicial + receitas − despesas − transferências enviadas + recebidas
 * Atual: só o que já foi efetivado (paid_at preenchido).
 * Previsto até uma data: inclui também os lançamentos pendentes até aquela data.
 * Resultado memorizado por request; limpo quando um lançamento é salvo/excluído.
 */
class AccountBalanceService
{
    /** @var array<string, array<int, int>> */
    private array $memo = [];

    /** @return array<int, int> account_id => saldo em centavos */
    public function current(): array
    {
        return $this->compute(null, false);
    }

    /** @return array<int, int> saldo projetado (efetivados + pendentes) até a data */
    public function projected(CarbonInterface $until): array
    {
        return $this->compute($until->toDateString(), true);
    }

    public function flush(): void
    {
        $this->memo = [];
    }

    /** @return array<int, int> */
    private function compute(?string $until, bool $includePending): array
    {
        $key = Auth::id().'|'.$until.'|'.(int) $includePending;

        return $this->memo[$key] ??= $this->query($until, $includePending);
    }

    /** @return array<int, int> */
    private function query(?string $until, bool $includePending): array
    {
        /** @var array<int, int> $balances */
        $balances = Account::query()->pluck('initial_balance', 'id')->map(fn ($v) => (int) $v)->all();

        $filter = function ($query) use ($until, $includePending) {
            if (! $includePending) {
                $query->whereNotNull('paid_at');
            }
            if ($until) {
                $query->where('date', '<=', $until);
            }
        };

        $transactions = Transaction::query()->whereNotNull('account_id')->tap($filter)
            ->selectRaw("account_id, SUM(CASE WHEN type = 'income' THEN amount ELSE -amount END) AS total")
            ->groupBy('account_id')->pluck('total', 'account_id');

        $outgoing = Transfer::query()->tap($filter)
            ->selectRaw('from_account_id, SUM(amount) AS total')->groupBy('from_account_id')->pluck('total', 'from_account_id');

        $incoming = Transfer::query()->tap($filter)
            ->selectRaw('to_account_id, SUM(amount) AS total')->groupBy('to_account_id')->pluck('total', 'to_account_id');

        foreach ($balances as $id => $initial) {
            $balances[$id] = $initial
                + (int) ($transactions[$id] ?? 0)
                - (int) ($outgoing[$id] ?? 0)
                + (int) ($incoming[$id] ?? 0);
        }

        return $balances;
    }
}
