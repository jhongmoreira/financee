<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\ActivityLogger;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Conta desativada pelo administrador: sai na hora, em qualquer aparelho. */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();
        if ($user instanceof User && ! $user->is_active) {
            app(ActivityLogger::class)->log('blocked', 'Acesso bloqueado: conta desativada', $user);
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => 'Esta conta foi desativada. Fale com o administrador.']);
        }

        return $next($request);
    }
}
