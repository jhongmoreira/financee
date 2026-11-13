<?php

use App\Console\Commands\PostDeploy;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

beforeEach(fn () => Process::fake());

afterEach(function () {
    File::delete([base_path('RELEASE'), storage_path('app/deployed_release')]);
});

it('não faz nada sem arquivo RELEASE', function () {
    $this->artisan('app:post-deploy')->assertSuccessful();

    Process::assertNothingRan();
    expect(File::exists(storage_path('app/deployed_release')))->toBeFalse();
});

it('aplica um release novo apenas uma vez, cada passo em processo separado', function () {
    File::put(base_path('RELEASE'), 'r1');

    $this->artisan('app:post-deploy')->expectsOutputToContain('Aplicando release r1')->assertSuccessful();

    foreach (PostDeploy::STEPS as $step) {
        if ($step === ['storage:link'] && File::exists(public_path('storage'))) {
            continue;
        }
        Process::assertRan(fn (PendingProcess $process) => $process->command === [PHP_BINARY, 'artisan', ...$step, '--no-interaction']);
    }
    expect(trim(File::get(storage_path('app/deployed_release'))))->toBe('r1');

    $this->artisan('app:post-deploy')->doesntExpectOutputToContain('Aplicando')->assertSuccessful();
});

it('não marca o release como aplicado se um passo falhar', function () {
    Process::fake(['*' => Process::result(errorOutput: 'erro de migration', exitCode: 1)]);
    File::put(base_path('RELEASE'), 'r2');

    $this->artisan('app:post-deploy')->assertFailed();

    expect(File::exists(storage_path('app/deployed_release')))->toBeFalse();
});
