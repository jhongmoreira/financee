<?php

namespace App\Livewire;

use App\Models\User;
use App\Notifications\FinanceAlert;
use App\Support\VapidKeys;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;
use NotificationChannels\WebPush\PushSubscription;

#[Title('Notificações')]
class Notifications extends Component
{
    /** @var array<string, bool> */
    public array $prefs = [];

    public int $dueDays = 1;

    public function mount(): void
    {
        $user = $this->user();
        foreach (array_keys(User::NOTIFICATION_TYPES) as $type) {
            $this->prefs[$type] = $user->wantsNotification($type);
        }
        $this->dueDays = $user->dueDaysBefore();
        $user->unreadNotifications()->update(['read_at' => now()]);
    }

    public function updatedPrefs(): void
    {
        $user = $this->user();
        foreach (array_keys(User::NOTIFICATION_TYPES) as $type) {
            $user->updateSetting('notifications.'.$type, (bool) ($this->prefs[$type] ?? true));
        }
        $this->dispatch('toast', message: 'Preferências salvas.');
    }

    public function updatedDueDays(): void
    {
        $this->dueDays = max(0, min(7, $this->dueDays));
        $this->user()->updateSetting('notifications.due_days', $this->dueDays);
        $this->dispatch('toast', message: 'Preferências salvas.');
    }

    public function sendTest(): void
    {
        $this->user()->notifyNow(new FinanceAlert('Teste do Financee', 'Tudo certo! Você vai receber os avisos por aqui.', route('notifications', absolute: false), 'bell', 'test'));
        $this->dispatch('toast', message: 'Aviso de teste enviado.');
    }

    public function removeDevice(int $id): void
    {
        $this->user()->pushSubscriptions()->whereKey($id)->delete();
        $this->dispatch('toast', message: 'Dispositivo removido.');
    }

    public function clearAll(): void
    {
        $this->user()->notifications()->delete();
    }

    public function render(): View
    {
        $user = $this->user();

        return view('livewire.notifications', [
            'publicKey' => VapidKeys::publicKey(),
            'devices' => $user->pushSubscriptions()->latest()->get()->map(fn (PushSubscription $s) => [
                'id' => $s->id,
                'name' => $this->deviceName((string) $s->endpoint),
                'since' => CarbonImmutable::parse((string) $s->getAttribute('created_at'))->format('d/m/Y'),
            ]),
            'items' => $user->notifications()->latest()->limit(30)->get(),
        ]);
    }

    private function deviceName(string $endpoint): string
    {
        $host = (string) parse_url($endpoint, PHP_URL_HOST);

        return match (true) {
            str_contains($host, 'apple') => 'iPhone / Mac (Safari)',
            str_contains($host, 'google') || str_contains($host, 'fcm') => 'Android / Chrome',
            str_contains($host, 'mozilla') => 'Firefox',
            str_contains($host, 'windows') || str_contains($host, 'microsoft') => 'Edge / Windows',
            default => $host ?: 'Dispositivo',
        };
    }

    private function user(): User
    {
        /** @var User */
        return Auth::user();
    }
}
