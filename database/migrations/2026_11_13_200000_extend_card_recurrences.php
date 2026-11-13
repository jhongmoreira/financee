<?php

use App\Models\Recurrence;
use App\Services\RecurrenceService;
use Illuminate\Database\Migrations\Migration;

/**
 * Fixos no cartão passaram a ser gerados 12 meses à frente (antes, só até o mês
 * seguinte). Gera agora os que já existem, sem esperar a rotina diária.
 */
return new class extends Migration
{
    public function up(): void
    {
        Recurrence::query()->withoutGlobalScopes()->whereNotNull('credit_card_id')
            ->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', today()->toDateString()))
            ->each(fn (Recurrence $r) => app(RecurrenceService::class)->generate($r));
    }

    public function down(): void
    {
        // nada a desfazer
    }
};
