<?php

namespace App\Livewire;

use App\Models\Account;
use App\Models\CreditCard;
use App\Models\Transaction;
use App\Models\Transfer;
use App\Services\MonthSummary;
use App\Support\Month;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Início')]
class Home extends Component
{
    #[On('entry-saved')]
    public function refresh(): void
    {
        // re-renderiza
    }

    public function togglePaid(string $kind, int $id): void
    {
        $model = $kind === 'transfer' ? Transfer::findOrFail($id) : Transaction::findOrFail($id);
        $model->update(['paid_at' => $model->paid_at ? null : min($model->date, today())]);
        $this->dispatch('toast', message: 'Marcado como efetivado.');
    }

    public function render(MonthSummary $summary): View
    {
        $month = CarbonImmutable::today()->startOfMonth();

        // Pendências: vencidas + próximos 7 dias
        $pending = Transaction::query()->with(['category', 'account'])->whereNull('paid_at')
            ->where('date', '<=', today()->addDays(7)->toDateString())
            ->orderBy('date')->limit(6)->get();

        $recent = Transaction::query()->with(['category', 'account'])->whereNotNull('paid_at')
            ->latest('date')->latest('id')->limit(5)->get();

        return view('livewire.home', [
            'monthLabel' => Month::label($month),
            'total' => $summary->currentTotal(),
            'projected' => $summary->projectedTotal($month),
            'totals' => $summary->totals($month),
            'accounts' => Account::query()->active()->ordered()->with('institution')->get(),
            'cardsCount' => CreditCard::query()->active()->count(),
            'pending' => $pending->map(fn (Transaction $t) => $this->row($t)),
            'recent' => $recent->map(fn (Transaction $t) => $this->row($t)),
        ]);
    }

    /** @return array<string, mixed> */
    private function row(Transaction $t): array
    {
        return [
            'kind' => 'transaction',
            'id' => $t->id,
            'date' => $t->date,
            'description' => $t->description,
            'subtitle' => collect([Month::dayLabel($t->date), $t->is_adjustment ? 'Ajuste de saldo' : $t->category?->name])->filter()->implode(' · '),
            'amount' => $t->signedAmount(),
            'paid' => $t->isPaid(),
            'overdue' => $t->isOverdue(),
            'icon' => $t->is_adjustment ? 'scale' : ($t->category->icon ?? 'tag'),
            'color' => $t->category->color ?? '#6b7280',
            'type' => $t->type,
        ];
    }
}
