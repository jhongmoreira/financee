<?php

namespace App\Livewire\Admin;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Histórico de atividades')]
class Activity extends Component
{
    #[Url]
    public string $usuario = '';

    #[Url]
    public string $tipo = '';

    #[Url]
    public string $de = '';

    #[Url]
    public string $ate = '';

    public int $limit = 100;

    public function more(): void
    {
        $this->limit = min($this->limit + 100, 2000);
    }

    public function updated(): void
    {
        $this->limit = 100;
    }

    public function render(): View
    {
        $date = fn (string $v) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null;

        $query = ActivityLog::query()->with('user')
            ->when(ctype_digit($this->usuario), fn (Builder $q) => $q->where('user_id', (int) $this->usuario))
            ->when($this->tipo !== '', fn (Builder $q) => $q->inGroup($this->tipo))
            ->when($date($this->de), fn (Builder $q, string $d) => $q->where('created_at', '>=', $d.' 00:00:00'))
            ->when($date($this->ate), fn (Builder $q, string $d) => $q->where('created_at', '<=', $d.' 23:59:59'));

        $logs = (clone $query)->latest('created_at')->latest('id')->limit($this->limit + 1)->get();

        return view('livewire.admin.activity', [
            'logs' => $logs->take($this->limit)->groupBy(fn (ActivityLog $l) => $l->created_at->toDateString()),
            'hasMore' => $logs->count() > $this->limit,
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
