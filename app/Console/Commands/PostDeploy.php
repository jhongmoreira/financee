<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Executado pelo scheduler (cron, a cada minuto). Quando detecta que uma nova
 * versão foi publicada (arquivo RELEASE diferente do último aplicado), roda as
 * migrations e recria os caches. Dispensa acesso SSH a cada deploy.
 */
class PostDeploy extends Command
{
    protected $signature = 'app:post-deploy {--force : Executa mesmo sem nova versão}';

    protected $description = 'Aplica migrations e caches quando uma nova versão é publicada';

    public function handle(): int
    {
        $releaseFile = base_path('RELEASE');

        if (! File::exists($releaseFile)) {
            return self::SUCCESS; // ambiente de desenvolvimento
        }

        $release = trim(File::get($releaseFile));
        $markerFile = storage_path('app/deployed_release');
        $deployed = File::exists($markerFile) ? trim(File::get($markerFile)) : null;

        if ($release === $deployed && ! $this->option('force')) {
            return self::SUCCESS;
        }

        $this->info("Aplicando release {$release}...");

        $this->call('migrate', ['--force' => true]);
        $this->call('optimize:clear');
        $this->call('optimize');

        if (! File::exists(public_path('storage'))) {
            $this->call('storage:link');
        }

        File::put($markerFile, $release);
        logger()->info("Release aplicado: {$release}");

        return self::SUCCESS;
    }
}
