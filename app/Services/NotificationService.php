<?php

namespace App\Services;

use App\Models\Category;
use App\Models\CreditCard;
use App\Models\InvestmentAsset;
use App\Models\Invoice;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\FinanceAlert;
use App\Support\Money;
use App\Support\Month;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Decide quais avisos mandar. Cada aviso tem uma chave única por usuário
 * (notification_logs), então rodar a rotina duas vezes não duplica nada.
 */
class NotificationService
{
    public function __construct(
        private InvoiceService $invoices,
        private BudgetService $budgets,
        private ForecastService $forecast,
    ) {}

    /** Envia um aviso uma única vez por chave. Retorna false se já tinha sido enviado. */
    public function sendOnce(User $user, string $key, FinanceAlert $alert): bool
    {
        try {
            DB::table('notification_logs')->insert(['user_id' => $user->id, 'key' => $key, 'sent_at' => now()]);
        } catch (QueryException) {
            return false; // já enviado
        }

        $user->notify($alert);

        return true;
    }

    /** Rotina diária (cron, 09:00). Retorna quantos avisos foram enviados. */
    public function runDaily(): int
    {
        $sent = 0;

        User::query()->each(function (User $user) use (&$sent) {
            // Os models filtram por usuário logado: "loga" o usuário durante a checagem.
            Auth::setUser($user);
            try {
                $sent += $this->dailyFor($user);
            } finally {
                Auth::forgetUser();
                app(AccountBalanceService::class)->flush();
            }
        });

        return $sent;
    }

    public function dailyFor(User $user): int
    {
        $today = CarbonImmutable::today();
        $sent = 0;

        if ($user->wantsNotification('due')) {
            $sent += $this->dueBills($user, $today);
        }
        if ($user->wantsNotification('invoices')) {
            $sent += $this->invoiceAlerts($user, $today);
        }
        if ($user->wantsNotification('forecast')) {
            $sent += $this->negativeForecast($user, $today);
        }
        if ($user->wantsNotification('investments') && $today->day === 1 && InvestmentAsset::query()->whereNull('archived_at')->exists()) {
            $sent += (int) $this->sendOnce($user, 'investments:'.Month::key($today), new FinanceAlert(
                'Hora de atualizar seus investimentos', 'Atualize o valor dos seus ativos para acompanhar a rentabilidade.', route('investments', absolute: false), 'arrow-trending-up', 'investments',
            ));
        }

        return $sent;
    }

    /** Alerta de orçamento (80% e 100%) após salvar uma despesa. */
    public function checkBudget(Transaction $transaction): void
    {
        $user = Auth::user();

        if (! $user instanceof User || $transaction->type !== 'expense' || ! $transaction->category_id || ! $user->wantsNotification('budget')) {
            return;
        }

        $category = Category::query()->find($transaction->category_id);
        if (! $category) {
            return;
        }
        $rootId = $category->parent_id ?? $category->id;
        $month = $transaction->effectiveDate()->startOfMonth(); // cartão: mês do vencimento da fatura
        $row = $this->budgets->forMonth($month)->first(fn (array $r) => $r['category']->id === $rootId);

        if (! $row) {
            return;
        }

        foreach ([100, 80] as $threshold) {
            if ($row['pct'] >= $threshold) {
                $this->sendOnce($user, "budget:{$rootId}:".Month::key($month).":{$threshold}", new FinanceAlert(
                    $threshold === 100 ? "Orçamento de {$row['category']->name} estourado" : "{$row['pct']}% do orçamento de {$row['category']->name}",
                    'Gasto: '.Money::format($row['spent']).' de '.Money::format($row['budget']).' em '.mb_strtolower(Month::label($month)).'.',
                    route('planning', absolute: false), 'chart-pie', 'budget-'.$rootId,
                ));
                break; // só o maior limite atingido
            }
        }
    }

    private function dueBills(User $user, CarbonImmutable $today): int
    {
        $sent = 0;
        $ahead = $today->addDays($user->dueDaysBefore());

        // Vencem hoje (e nos próximos dias, conforme a preferência)
        $upcoming = Transaction::query()->whereNull('paid_at')->whereNotNull('account_id')
            ->whereBetween('date', [$today->toDateString(), $ahead->toDateString()])->orderBy('date')->get();

        foreach ($upcoming->groupBy(fn (Transaction $t) => $t->date->toDateString()) as $day => $items) {
            $date = CarbonImmutable::parse($day);
            $when = $date->isToday() ? 'hoje' : ($date->isTomorrow() ? 'amanhã' : 'em '.$date->format('d/m'));
            $expenses = $items->where('type', 'expense');
            $incomes = $items->where('type', 'income');

            if ($expenses->isNotEmpty()) {
                $sent += (int) $this->sendOnce($user, "due:{$day}:expense:".($date->isToday() ? 'today' : 'before'), new FinanceAlert(
                    $expenses->count() === 1 ? 'Conta vence '.$when : $expenses->count().' contas vencem '.$when,
                    $this->listBody($expenses),
                    route('transactions', ['mes' => Month::key($date), 'status' => 'pending'], false), 'clock', 'due-'.$day,
                ));
            }
            if ($incomes->isNotEmpty() && $date->isToday()) {
                $sent += (int) $this->sendOnce($user, "due:{$day}:income", new FinanceAlert(
                    $incomes->count() === 1 ? 'Recebimento previsto hoje' : $incomes->count().' recebimentos previstos hoje',
                    $this->listBody($incomes),
                    route('transactions', ['mes' => Month::key($date), 'status' => 'pending'], false), 'banknotes', 'income-'.$day,
                ));
            }
        }

        // Venceram ontem e continuam pendentes
        $overdue = Transaction::query()->whereNull('paid_at')->whereNotNull('account_id')->where('type', 'expense')
            ->where('date', $today->subDay()->toDateString())->get();

        if ($overdue->isNotEmpty()) {
            $sent += (int) $this->sendOnce($user, 'overdue:'.$today->subDay()->toDateString(), new FinanceAlert(
                $overdue->count() === 1 ? 'Conta vencida ontem' : $overdue->count().' contas venceram ontem',
                $this->listBody($overdue).' Já pagou? Marque como paga no app.',
                route('transactions', ['status' => 'pending'], false), 'exclamation-triangle', 'overdue',
            ));
        }

        return $sent;
    }

    private function invoiceAlerts(User $user, CarbonImmutable $today): int
    {
        $sent = 0;
        $cards = CreditCard::query()->active()->pluck('id');
        $invoices = Invoice::query()->with('creditCard')->whereIn('credit_card_id', $cards)
            ->where(fn ($q) => $q->where('closing_date', $today->toDateString())
                ->orWhereBetween('due_date', [$today->toDateString(), $today->addDays(2)->toDateString()]))
            ->get();
        $summaries = $this->invoices->summaries($invoices);

        foreach ($invoices as $invoice) {
            $s = $summaries[$invoice->id];
            $name = $invoice->creditCard->name ?? 'Cartão';
            $url = route('cards.invoice', ['card' => $invoice->credit_card_id, 'mes' => $invoice->reference_month], false);

            if ($invoice->closing_date->isSameDay($today) && $s['total'] > 0) {
                $sent += (int) $this->sendOnce($user, "invoice:{$invoice->id}:closed", new FinanceAlert(
                    "Fatura {$name} fechou", 'Total de '.Money::format($s['total']).', vence em '.$invoice->due_date->format('d/m').'.', $url, 'credit-card', 'invoice-'.$invoice->id,
                ));
            }

            if ($s['remaining'] > 0 && $invoice->due_date->greaterThanOrEqualTo($today) && $invoice->due_date->lessThanOrEqualTo($today->addDays(2))) {
                $when = $invoice->due_date->isToday() ? 'hoje' : ($invoice->due_date->isTomorrow() ? 'amanhã' : 'em '.$invoice->due_date->format('d/m'));
                $sent += (int) $this->sendOnce($user, "invoice:{$invoice->id}:due:".($invoice->due_date->isToday() ? 'today' : 'before'), new FinanceAlert(
                    "Fatura {$name} vence {$when}", 'Falta pagar '.Money::format($s['remaining']).'.', $url, 'credit-card', 'invoice-'.$invoice->id,
                ));
            }
        }

        return $sent;
    }

    private function negativeForecast(User $user, CarbonImmutable $today): int
    {
        $negative = collect($this->forecast->months(3))->first(fn (array $m) => $m['end'] < 0);

        if (! $negative) {
            return 0;
        }

        return (int) $this->sendOnce($user, 'forecast:'.$today->format('o-W'), new FinanceAlert(
            'Atenção: saldo previsto negativo',
            'Em '.mb_strtolower($negative['label']).' o saldo previsto fica em '.Money::format($negative['end']).'.',
            route('planning', ['aba' => 'previsao'], false), 'exclamation-triangle', 'forecast',
        ));
    }

    /** @param Collection<int, Transaction> $items */
    private function listBody($items): string
    {
        $total = Money::format((int) $items->sum('amount'));
        $names = $items->take(3)->pluck('description')->implode(', ');

        return $names.($items->count() > 3 ? ' e outras' : '').' · total '.$total.'.';
    }
}
