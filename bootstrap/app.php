<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

/*
| Em produção (Hostoo), o .env, o banco SQLite e o storage ficam em
| "../financee-data", fora da pasta sincronizada pela integração Git.
| Assim um deploy nunca sobrescreve nem apaga dados. Em desenvolvimento
| essa pasta não existe e tudo continua na raiz do projeto.
*/
$dataPath = dirname(__DIR__, 2).'/financee-data';

if (is_file($dataPath.'/.env')) {
    $app->useEnvironmentPath($dataPath);
}

if (is_dir($dataPath.'/storage')) {
    $app->useStoragePath($dataPath.'/storage');
}

return $app;
