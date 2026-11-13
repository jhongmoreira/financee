<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Remove o horário ("00:00:00") das colunas de data, que agora são gravadas
 * como "YYYY-MM-DD" pelo cast App\Casts\DateOnly.
 */
return new class extends Migration
{
    private const COLUMNS = [
        'transactions' => ['date', 'paid_at'],
        'transfers' => ['date', 'paid_at'],
        'invoices' => ['closing_date', 'due_date'],
        'invoice_payments' => ['paid_at'],
        'installment_plans' => ['first_date'],
        'recurrences' => ['start_date', 'end_date', 'generated_until'],
        'investment_movements' => ['date'],
        'investment_assets' => ['maturity_date', 'current_value_at'],
    ];

    public function up(): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            foreach ($columns as $column) {
                DB::table($table)->whereNotNull($column)->where($column, 'like', '% %')
                    ->update([$column => DB::raw("substr({$column}, 1, 10)")]);
            }
        }
    }

    public function down(): void
    {
        // sem volta: o formato só com a data é o correto
    }
};
