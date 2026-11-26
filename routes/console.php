<?php

use App\Models\ActivityLog;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\RecurrenceService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Str;

/*
| Cron da Hostoo (uma única linha):
| * * * * * /usr/local/bin/ea-php84 ~/public_html/financee/artisan schedule:run >> /dev/null 2>&1
*/

// Aplica migrations/caches quando um novo release chega pela integração Git.
// Na VPS o deploy-app já roda o app:post-deploy a cada deploy (e grava .deployed-sha no release):
// lá não precisa subir um PHP a cada minuto só para isso. Na Hostoo continua igual.
if (! file_exists(base_path('.deployed-sha'))) {
    Schedule::command('app:post-deploy')
        ->everyMinute()
        ->withoutOverlapping();
}

// Hospedagem compartilhada não tem supervisor: processa a fila a cada minuto.
// Só sobe o queue:work quando há job esperando (economiza um PHP por minuto).
// Se a consulta falhar por qualquer motivo, roda como antes.
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')
    ->everyMinute()
    ->withoutOverlapping()
    ->when(function () {
        try {
            return DB::table(config('queue.connections.database.table', 'jobs'))
                ->where('available_at', '<=', now()->getTimestamp())->exists();
        } catch (Throwable) {
            return true;
        }
    });

// Lançamentos fixos: estende a geração (até 31/12 do ano seguinte) e efetiva débitos automáticos.
Artisan::command('recurrences:generate', function (RecurrenceService $service) {
    $this->info($service->runDaily().' lançamento(s) fixo(s) gerado(s).');
})->purpose('Gera lançamentos fixos futuros e efetiva débitos automáticos');

Schedule::command('recurrences:generate')->dailyAt('03:10')->withoutOverlapping();

// Avisos do dia (contas a vencer, faturas, saldo negativo, investimentos). Push sai pela fila.
Artisan::command('notifications:send', function (NotificationService $service) {
    $this->info($service->runDaily().' aviso(s) enviado(s).');
})->purpose('Envia os avisos diários (push e lista no app)');

Schedule::command('notifications:send')->dailyAt('09:00')->withoutOverlapping();

// Manutenção do SQLite: atualiza estatísticas (semanal) e compacta o arquivo (mensal).
Artisan::command('app:db-maintenance {--vacuum}', function () {
    DB::statement('PRAGMA optimize');
    if ($this->option('vacuum')) {
        DB::statement('VACUUM');
    }
    $this->info('Banco otimizado.');
})->purpose('PRAGMA optimize (e VACUUM com --vacuum)');

Schedule::command('app:db-maintenance')->weeklyOn(0, '04:00')->withoutOverlapping();
Schedule::command('app:db-maintenance --vacuum')->monthlyOn(1, '04:30')->withoutOverlapping();

// Histórico de atividades: guarda 180 dias (roda dentro do schedule:run, sem subir outro PHP)
Schedule::call(fn () => ActivityLog::query()->where('created_at', '<', now()->subDays(180))->delete())
    ->name('activity:prune')->dailyAt('03:40')->withoutOverlapping();

// Cria (ou promove) o super usuário. Sem --password, gera uma senha temporária e mostra na tela.
Artisan::command('app:create-admin {email} {--name=} {--password=}', function (string $email) {
    $email = mb_strtolower(trim($email));
    $password = (string) ($this->option('password') ?: Str::password(14, symbols: false));
    $user = User::query()->where('email', $email)->first();

    if ($user) {
        $user->update(['is_admin' => true, 'is_active' => true]);
        $this->info("{$email} agora é administrador (senha mantida).");

        return 0;
    }

    $user = User::query()->create([
        'name' => $this->option('name') ?: 'Administrador',
        'email' => $email,
        'password' => Hash::make($password),
        'is_admin' => true,
    ]);
    event(new Registered($user)); // categorias e carteira padrão
    $this->info("Administrador criado: {$email}");
    $this->line("Senha temporária: {$password}");

    return 0;
})->purpose('Cria ou promove o super usuário (administrador)');
