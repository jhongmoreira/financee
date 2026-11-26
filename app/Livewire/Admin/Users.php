<?php

namespace App\Livewire\Admin;

use App\Models\User;
use App\Services\ActivityLogger;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Super usuário: lista as contas e gerencia o acesso. Não mostra dados financeiros
 * (só quantos registros cada um tem); as consultas aqui não passam pelo filtro por
 * usuário, por isso usam DB::table com o user_id explícito.
 */
#[Title('Usuários')]
class Users extends Component
{
    public ?int $deleting = null;

    public string $confirmEmail = '';

    public function toggleActive(int $id, ActivityLogger $logger): void
    {
        $user = $this->other($id);
        $user->update(['is_active' => ! $user->is_active]);
        if (! $user->is_active) {
            DB::table('sessions')->where('user_id', $user->id)->delete(); // sai de todos os aparelhos
        }
        $logger->log('admin', ($user->is_active ? 'Reativou' : 'Desativou').' a conta de '.$user->email);
        $this->dispatch('toast', message: $user->is_active ? 'Conta reativada.' : 'Conta desativada: a pessoa saiu de todos os aparelhos.');
    }

    public function sendReset(int $id, ActivityLogger $logger): void
    {
        $user = User::query()->findOrFail($id);
        $status = Password::sendResetLink(['email' => $user->email]);
        $ok = $status === Password::RESET_LINK_SENT;
        if ($ok) {
            $logger->log('admin', 'Enviou link de redefinição de senha para '.$user->email);
        }
        $this->dispatch('toast', message: $ok ? 'Link enviado para '.$user->email.'.' : 'Não foi possível enviar agora ('.__($status).').', type: $ok ? 'success' : 'error');
    }

    public function askDelete(int $id): void
    {
        $this->other($id);
        $this->deleting = $id;
        $this->confirmEmail = '';
        $this->resetValidation();
        $this->dispatch('open-sheet', 'delete-user');
    }

    public function deleteUser(ActivityLogger $logger): void
    {
        $user = $this->other((int) $this->deleting);
        if (mb_strtolower(trim($this->confirmEmail)) !== mb_strtolower($user->email)) {
            $this->addError('confirmEmail', 'Digite o e-mail exatamente como aparece para confirmar.');

            return;
        }

        $email = $user->email;
        DB::transaction(function () use ($user) {
            // Lançamentos, contas, cartões etc. saem junto pelo ON DELETE CASCADE do banco
            $user->notifications()->delete();
            $user->pushSubscriptions()->delete();
            DB::table('sessions')->where('user_id', $user->id)->delete();
            $user->delete();
        });
        $logger->log('admin', 'Excluiu a conta de '.$email.' e todos os dados dela');

        $this->deleting = null;
        $this->dispatch('close-sheet', 'delete-user');
        $this->dispatch('toast', message: 'Conta excluída.');
    }

    public function render(): View
    {
        $count = fn (string $table) => DB::table($table)->selectRaw('user_id, count(*) as total')->groupBy('user_id')->pluck('total', 'user_id');
        $transactions = $count('transactions');
        $accounts = $count('accounts');
        $lastSeen = DB::table('activity_logs')->whereNotNull('user_id')->selectRaw('user_id, max(created_at) as last')->groupBy('user_id')->pluck('last', 'user_id');

        $users = User::query()->orderByDesc('is_admin')->orderBy('name')->get()->map(fn (User $u) => [
            'user' => $u,
            'transactions' => (int) ($transactions[$u->id] ?? 0),
            'accounts' => (int) ($accounts[$u->id] ?? 0),
            'last_seen' => isset($lastSeen[$u->id]) ? CarbonImmutable::parse((string) $lastSeen[$u->id]) : null,
        ]);

        return view('livewire.admin.users', [
            'rows' => $users,
            'me' => Auth::id(),
            'deletingUser' => $this->deleting ? User::query()->find($this->deleting) : null,
        ]);
    }

    /** Outra conta (o administrador não desativa nem exclui a própria). */
    private function other(int $id): User
    {
        abort_if($id === Auth::id(), 422, 'Não é possível fazer isso com a própria conta.');

        return User::query()->findOrFail($id);
    }
}
