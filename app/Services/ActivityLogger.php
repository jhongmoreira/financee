<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Grava o histórico de atividades. NUNCA derruba a requisição: se gravar o
 * histórico falhar, o erro vai para o log e o app segue normalmente.
 */
class ActivityLogger
{
    /** Nome amigável dos registros (o histórico mostra o nome, nunca valores). */
    public const SUBJECTS = [
        'Transaction' => 'Lançamento', 'Account' => 'Conta', 'AccountCategory' => 'Grupo de contas', 'CreditCard' => 'Cartão',
        'Category' => 'Categoria', 'Recurrence' => 'Lançamento fixo', 'Budget' => 'Orçamento', 'Invoice' => 'Fatura',
        'InvoicePayment' => 'Pagamento de fatura', 'InstallmentPlan' => 'Parcelamento', 'Transfer' => 'Transferência',
        'InvestmentAsset' => 'Ativo', 'InvestmentMovement' => 'Movimentação de investimento', 'InvestmentPortfolio' => 'Carteira de investimentos',
    ];

    /** Campos que não contam como "edição" (mudam sozinhos). */
    private const IGNORED_FIELDS = ['updated_at', 'created_at', 'remember_token'];

    /** @param array<string, mixed> $meta */
    public function log(string $event, string $description, ?User $user = null, array $meta = [], ?Model $subject = null, ?string $email = null): void
    {
        try {
            $user ??= Auth::user() instanceof User ? Auth::user() : null;
            $request = app()->runningInConsole() ? null : request();

            ActivityLog::query()->create([
                'user_id' => $user?->id,
                'user_email' => $email ?? $user?->email,
                'event' => $event,
                'description' => Str::limit($description, 250, '…'),
                'subject_type' => $subject ? class_basename($subject) : null,
                'subject_id' => $subject?->getKey(),
                'url' => $request ? Str::limit('/'.ltrim($request->path(), '/'), 250, '') : null,
                'ip' => $request?->ip(),
                'user_agent' => $request ? Str::limit((string) $request->userAgent(), 250, '') : null,
                'meta' => $meta ?: null,
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::warning('Falha ao gravar o histórico de atividades: '.$e->getMessage());
        }
    }

    /** Criação/edição/exclusão de um registro do usuário logado (chamado pelo trait BelongsToUser). */
    public function model(Model $model, string $event): void
    {
        if (! Auth::check()) {
            return; // rotinas automáticas (cron) não entram no histórico
        }

        $fields = [];
        if ($event === 'updated') {
            $fields = array_values(array_diff(array_keys($model->getChanges()), self::IGNORED_FIELDS));
            if ($fields === []) {
                return;
            }
        }

        $type = self::SUBJECTS[class_basename($model)] ?? class_basename($model);
        $name = $this->nameOf($model);
        $verb = ['created' => 'Criou', 'updated' => 'Editou', 'deleted' => 'Excluiu'][$event] ?? $event;

        $this->log($event, $verb.' '.mb_strtolower($type).($name ? ' “'.$name.'”' : ''), null, $fields ? ['campos' => $fields] : [], $model);
    }

    private function nameOf(Model $model): ?string
    {
        foreach (['description', 'name', 'ticker'] as $attribute) {
            $value = $model->getAttributes()[$attribute] ?? null;
            if (is_string($value) && trim($value) !== '') {
                return Str::limit(trim($value), 60, '…');
            }
        }

        return null;
    }
}
