<?php

use App\Services\NotificationService;
use App\Services\RecurrenceService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
| Cron da Hostoo (uma única linha):
| * * * * * /usr/local/bin/ea-php84 ~/public_html/financee/artisan schedule:run >> /dev/null 2>&1
*/

// Aplica migrations/caches quando um novo release chega pela integração Git.
Schedule::command('app:post-deploy')
    ->everyMinute()
    ->withoutOverlapping();

// Hospedagem compartilhada não tem supervisor: processa a fila a cada minuto.
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')
    ->everyMinute()
    ->withoutOverlapping();

// Lançamentos fixos: estende a geração (12 meses à frente) e efetiva débitos automáticos.
Artisan::command('recurrences:generate', function (RecurrenceService $service) {
    $this->info($service->runDaily().' lançamento(s) fixo(s) gerado(s).');
})->purpose('Gera lançamentos fixos futuros e efetiva débitos automáticos');

Schedule::command('recurrences:generate')->dailyAt('03:10')->withoutOverlapping();

// Avisos do dia (contas a vencer, faturas, saldo negativo, investimentos). Push sai pela fila.
Artisan::command('notifications:send', function (NotificationService $service) {
    $this->info($service->runDaily().' aviso(s) enviado(s).');
})->purpose('Envia os avisos diários (push e lista no app)');

Schedule::command('notifications:send')->dailyAt('09:00')->withoutOverlapping();
