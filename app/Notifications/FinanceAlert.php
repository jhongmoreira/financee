<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Aviso do Financee: vai como push (nos dispositivos ativados) e fica na
 * lista de notificações do app. Enviado pela fila (processada pelo cron).
 */
class FinanceAlert extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $title,
        public string $body,
        public string $url = '/',
        public string $icon = 'bell',
        public ?string $tag = null,
    ) {}

    /** @return list<string> */
    public function via(User $notifiable): array
    {
        return $notifiable->pushSubscriptions()->exists()
            ? ['database', WebPushChannel::class]
            : ['database'];
    }

    public function toWebPush(User $notifiable, Notification $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title($this->title)
            ->body($this->body)
            ->icon('/icons/icon-192.png')
            ->badge('/icons/favicon-32.png')
            ->tag($this->tag ?? 'financee')
            ->data(['url' => $this->url]);
    }

    /** @return array<string, string> */
    public function toArray(User $notifiable): array
    {
        return ['title' => $this->title, 'body' => $this->body, 'url' => $this->url, 'icon' => $this->icon];
    }
}
