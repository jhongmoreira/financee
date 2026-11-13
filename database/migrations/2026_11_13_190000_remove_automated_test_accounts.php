<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Remove contas criadas pelos testes automatizados de ponta a ponta em produção
 * (e-mail teste-automatico-*@financee.test — o domínio .test é reservado e nunca
 * pertence a um usuário real). Os dados ligados a elas são apagados em cascata.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->where('email', 'like', 'teste-automatico-%@financee.test')->delete();
    }

    public function down(): void
    {
        // nada a restaurar
    }
};
