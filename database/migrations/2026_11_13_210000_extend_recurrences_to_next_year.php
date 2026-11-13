<?php

use App\Models\Recurrence;
use App\Services\RecurrenceService;
use Illuminate\Database\Migrations\Migration;

/**
 * O horizonte dos lançamentos fixos passou de 12 meses para "até 31/12 do ano
 * seguinte". Estende agora os fixos existentes, sem esperar a rotina diária.
 */
return new class extends Migration
{
    public function up(): void
    {
        Recurrence::query()->withoutGlobalScopes()
            ->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', today()->toDateString()))
            ->each(fn (Recurrence $r) => app(RecurrenceService::class)->generate($r));
    }

    public function down(): void
    {
        // nada a desfazer
    }
};
