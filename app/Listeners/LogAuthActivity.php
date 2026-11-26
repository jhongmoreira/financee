<?php

namespace App\Listeners;

use App\Http\Middleware\LogActivity;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;

/**
 * Acessos no histórico. "Entrou automaticamente" é o login pelo "lembrar de mim":
 * o PWA reabre sem pedir senha, e o Laravel dispara o Login com remember = true.
 */
class LogAuthActivity
{
    public function __construct(private ActivityLogger $logger) {}

    public function handleLogin(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }
        // Pelo formulário o login acontece em /login; em qualquer outra URL foi o cookie "lembrar de mim".
        $auto = $event->remember && ! request()->is('login');
        $this->logger->log($auto ? 'login_auto' : 'login',
            $auto ? 'Entrou automaticamente (sessão lembrada no aparelho)' : 'Entrou com e-mail e senha',
            $event->user);
        LogActivity::touch();
    }

    public function handleLogout(Logout $event): void
    {
        if ($event->user instanceof User) {
            $this->logger->log('logout', 'Saiu do app', $event->user);
        }
    }

    public function handleFailed(Failed $event): void
    {
        $email = (string) ($event->credentials['email'] ?? '');
        $user = $event->user instanceof User ? $event->user : null;
        $this->logger->log('login_failed', 'Tentativa de login com senha errada'.($user ? '' : ' (e-mail não cadastrado)'), $user, [], null, $email !== '' ? mb_substr($email, 0, 120) : null);
    }

    public function handlePasswordReset(PasswordReset $event): void
    {
        if ($event->user instanceof User) {
            $this->logger->log('password_reset', 'Redefiniu a senha pelo link do e-mail', $event->user);
        }
    }

    public function handleRegistered(Registered $event): void
    {
        if ($event->user instanceof User) {
            $this->logger->log('registered', 'Criou a conta', $event->user);
        }
    }
}
