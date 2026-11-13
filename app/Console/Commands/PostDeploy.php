<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * Executado pelo scheduler (cron, a cada minuto). Quando detecta que uma nova
 * versão foi publicada (arquivo RELEASE diferente do último aplicado), roda as
 * migrations e recria os caches. Dispensa acesso SSH a cada deploy.
 *
 * Cada passo roda em um PROCESSO NOVO do PHP: este comando foi iniciado com a
 * lista de pacotes/providers antiga, e gerar caches aqui dentro gravaria views
 * e config sem os pacotes novos do release (ex.: diretiva @svg aparecendo como texto).
 */
class PostDeploy extends Command
{
    protected $signature = 'app:post-deploy {--force : Executa mesmo sem nova versão}';

    protected $description = 'Aplica migrations e caches quando uma nova versão é publicada';

    /** @var list<list<string>> */
    public const STEPS = [
        ['optimize:clear'],
        ['package:discover'],
        ['migrate', '--force'],
        ['optimize'],
        ['storage:link'],
    ];

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

        foreach (self::STEPS as $step) {
            if ($step === ['storage:link'] && File::exists(public_path('storage'))) {
                continue;
            }

            $result = Process::path(base_path())
                ->timeout(300)
                ->run([PHP_BINARY, 'artisan', ...$step, '--no-interaction']);

            $this->line(trim($result->output()));

            if ($result->failed()) {
                $this->error('Falhou: artisan '.implode(' ', $step).PHP_EOL.$result->errorOutput());
                logger()->error("Post-deploy falhou em '".implode(' ', $step)."'", ['output' => $result->errorOutput()]);

                return self::FAILURE; // não grava o marcador: o cron tenta de novo no próximo minuto
            }
        }

        File::put($markerFile, $release);
        logger()->info("Release aplicado: {$release}");

        return self::SUCCESS;
    }
}
