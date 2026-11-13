<?php

use Illuminate\Support\Facades\File;

afterEach(function () {
    File::delete([base_path('RELEASE'), storage_path('app/deployed_release')]);
    $this->artisan('optimize:clear'); // não deixar cache de config do ambiente de teste
});

it('não faz nada sem arquivo RELEASE', function () {
    $this->artisan('app:post-deploy')->assertSuccessful();
    expect(File::exists(storage_path('app/deployed_release')))->toBeFalse();
});

it('aplica um release novo apenas uma vez', function () {
    File::put(base_path('RELEASE'), 'r1');

    $this->artisan('app:post-deploy')->expectsOutputToContain('Aplicando release r1')->assertSuccessful();
    expect(trim(File::get(storage_path('app/deployed_release'))))->toBe('r1');

    $this->artisan('app:post-deploy')->doesntExpectOutputToContain('Aplicando')->assertSuccessful();
});
