<?php

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;

function everyMinuteEvent(string $needle): ?Event
{
    return collect(app(Schedule::class)->events())
        ->first(fn ($e) => $e->expression === '* * * * *' && str_contains((string) $e->command, $needle));
}

it('só sobe o queue:work quando há job esperando', function () {
    $queue = everyMinuteEvent('queue:work');

    expect($queue->filtersPass(app()))->toBeFalse();

    DB::table('jobs')->insert(['queue' => 'default', 'payload' => '{}', 'attempts' => 0, 'available_at' => now()->getTimestamp(), 'created_at' => now()->getTimestamp()]);
    expect($queue->filtersPass(app()))->toBeTrue();
});

it('não sobe o queue:work por job agendado para o futuro', function () {
    DB::table('jobs')->insert(['queue' => 'default', 'payload' => '{}', 'attempts' => 0, 'available_at' => now()->addHour()->getTimestamp(), 'created_at' => now()->getTimestamp()]);

    expect(everyMinuteEvent('queue:work')->filtersPass(app()))->toBeFalse();
});

it('fora da VPS (sem .deployed-sha, ex.: Hostoo) mantém o app:post-deploy a cada minuto', function () {
    expect(file_exists(base_path('.deployed-sha')))->toBeFalse()
        ->and(everyMinuteEvent('app:post-deploy'))->not->toBeNull();
});
