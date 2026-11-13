<?php

namespace App\Livewire;

use App\Services\ForecastService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Planejamento')]
class Planning extends Component
{
    #[On('entry-saved')]
    public function refresh(): void {}

    public function render(ForecastService $forecast): View
    {
        $months = $forecast->months(12);
        $values = array_column($months, 'end');
        $max = max(1, ...array_map('abs', $values));
        $firstNegative = collect($months)->first(fn (array $m) => $m['end'] < 0);

        return view('livewire.planning', compact('months', 'max', 'firstNegative'));
    }
}
