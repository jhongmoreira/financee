<?php

namespace App\Providers;

use App\Support\VapidKeys;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use NotificationChannels\WebPush\WebPushChannel;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Date::use(CarbonImmutable::class);

        // Em dev: erro em lazy loading (N+1), atributos inexistentes e mass assignment silencioso.
        Model::shouldBeStrict(! $this->app->isProduction());

        if ($this->app->isProduction()) {
            URL::forceHttps();
        }

        // SQLite: ao excluir, sobrescreve com zeros o conteúdo apagado no arquivo do banco
        // (sem isso, bytes antigos ficariam no espaço livre até o VACUUM mensal).
        Event::listen(ConnectionEstablished::class, function (ConnectionEstablished $event) {
            if ($event->connection->getDriverName() === 'sqlite') {
                $event->connection->statement('PRAGMA secure_delete = ON');
            }
        });

        // Web Push: garante as chaves VAPID (geradas e guardadas no storage se não houver no .env)
        $this->app->beforeResolving(WebPushChannel::class, fn () => VapidKeys::ensure());
    }
}
