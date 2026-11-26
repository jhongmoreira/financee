<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\ActivityLogger;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Navegação no histórico: cada página aberta (inclusive pelo wire:navigate) e
 * downloads. Ações dentro da tela (Livewire) não entram aqui; criações, edições
 * e exclusões são registradas pelos models.
 *
 * O PWA não pede login ao reabrir: quando a pessoa volta depois de 30 min sem
 * usar, registra "Abriu o app".
 */
class LogActivity
{
    private const SESSION_KEY = 'activity.last_seen';

    private const RESUME_AFTER_SECONDS = 1800;

    /** Páginas (nome da rota => descrição). */
    public const PAGES = [
        'home' => 'Início', 'transactions' => 'Lançamentos', 'planning' => 'Planejamento', 'more' => 'Mais',
        'profile' => 'Perfil', 'accounts' => 'Contas', 'cards' => 'Cartões', 'cards.invoice' => 'Fatura do cartão',
        'categories' => 'Categorias', 'recurrences' => 'Lançamentos fixos', 'investments' => 'Investimentos',
        'investments.asset' => 'Ativo de investimento', 'reports' => 'Relatórios', 'notifications' => 'Notificações',
        'export.csv' => 'Exportou lançamentos (CSV)', 'export.pdf' => 'Exportou relatório (PDF)', 'export.backup' => 'Baixou o backup (JSON)',
        'admin.users' => 'Administração: usuários', 'admin.activity' => 'Administração: histórico',
    ];

    public function __construct(private ActivityLogger $logger) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        try {
            $this->record($request, $response);
        } catch (\Throwable) {
            // o histórico nunca atrapalha a navegação
        }

        return $response;
    }

    /** Marca a sessão como ativa agora (chamado também no login). */
    public static function touch(): void
    {
        if (app()->bound('session.store') && request()->hasSession()) {
            request()->session()->put(self::SESSION_KEY, now()->getTimestamp());
        }
    }

    private function record(Request $request, Response $response): void
    {
        $user = Auth::user();
        if (! $user instanceof User || ! $request->hasSession()) {
            return;
        }

        $last = (int) $request->session()->get(self::SESSION_KEY, 0);
        if (now()->getTimestamp() - $last > self::RESUME_AFTER_SECONDS && ! $request->is('login', 'register')) {
            $this->logger->log('app_opened', 'Abriu o app', $user);
        }
        self::touch();

        $route = $request->route()?->getName();
        if (! $request->isMethod('GET') || $route === null || ! isset(self::PAGES[$route]) || $response->getStatusCode() >= 400) {
            return;
        }

        $label = self::PAGES[$route];
        $this->logger->log('page_view', str_starts_with($label, 'Exportou') || str_starts_with($label, 'Baixou') ? $label : 'Abriu '.$label, $user);
    }
}
