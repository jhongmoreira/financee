<?php

use Illuminate\Support\Facades\Schedule;

/*
| Cron da Hostoo (uma única linha):
| * * * * * /usr/local/bin/ea-php84 ~/financee/artisan schedule:run >> /dev/null 2>&1
*/

// Aplica migrations/caches quando um novo release chega pela integração Git.
Schedule::command('app:post-deploy')
    ->everyMinute()
    ->withoutOverlapping();

// Hospedagem compartilhada não tem supervisor: processa a fila a cada minuto.
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')
    ->everyMinute()
    ->withoutOverlapping();
