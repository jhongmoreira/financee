<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * "Recomeçar do zero": apaga todos os dados financeiros do usuário e recria a
 * estrutura inicial (categorias padrão e conta Carteira), como num cadastro novo.
 * Mantém o login, perfil, preferências e os dispositivos de notificação.
 */
class UserDataReset
{
    /** Tabelas na ordem de exclusão (dependentes primeiro, por causa das chaves estrangeiras). */
    private const TABLES = [
        'transactions',
        'transfers',
        'invoice_payments',
        'investment_movements',
        'budgets',
        'invoices',
        'installment_plans',
        'recurrences',
        'investment_assets',
        'investment_portfolios',
        'credit_cards',
        'accounts',
        'notification_logs',
    ];

    public function __construct(
        private DefaultUserData $defaults,
        private AccountBalanceService $balances,
    ) {}

    public function reset(User $user): void
    {
        DB::transaction(function () use ($user) {
            foreach (self::TABLES as $table) {
                DB::table($table)->where('user_id', $user->id)->delete();
            }

            // Subcategorias primeiro, depois as principais
            DB::table('categories')->where('user_id', $user->id)->whereNotNull('parent_id')->delete();
            DB::table('categories')->where('user_id', $user->id)->delete();

            $user->notifications()->delete();

            $this->defaults->createFor($user);
        });

        $this->balances->flush();
    }
}
