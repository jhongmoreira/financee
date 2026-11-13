<?php

namespace App\Livewire\Reports;

use App\Services\ReportService;
use App\Support\Month;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Relatórios')]
class Index extends Component
{
    public const PRESETS = [
        'month' => 'Este mês',
        'last_month' => 'Mês passado',
        'last_3' => 'Últimos 3 meses',
        'last_6' => 'Últimos 6 meses',
        'year' => 'Este ano',
        'last_12' => 'Últimos 12 meses',
        'custom' => 'Personalizado',
    ];

    public const TABS = ['despesas' => 'Despesas', 'receitas' => 'Receitas', 'evolucao' => 'Evolução', 'saldo' => 'Saldo', 'cartoes' => 'Cartões'];

    #[Url(as: 'relatorio', except: 'despesas')]
    public string $tab = 'despesas';

    #[Url(as: 'periodo', except: 'month')]
    public string $preset = 'month';

    #[Url(as: 'inicio', except: '')]
    public string $start = '';

    #[Url(as: 'fim', except: '')]
    public string $end = '';

    #[Url(as: 'regime', except: 'competencia')]
    public string $regime = 'competencia';

    public function mount(): void
    {
        $this->tab = array_key_exists($this->tab, self::TABS) ? $this->tab : 'despesas';
        $this->preset = array_key_exists($this->preset, self::PRESETS) ? $this->preset : 'month';
        $this->regime = in_array($this->regime, ['competencia', 'caixa'], true) ? $this->regime : 'competencia';
    }

    #[On('entry-saved')]
    public function refresh(): void {}

    /** Os valores vêm do navegador: normaliza qualquer coisa inesperada. */
    public function updated(string $property): void
    {
        $this->mount();
    }

    public function render(ReportService $reports): View
    {
        [$start, $end] = ReportService::presetRange($this->preset, $this->start, $this->end);
        if ($start->diffInMonths($end) > 60) {
            $start = $end->subMonthsNoOverflow(60)->startOfMonth(); // limite de 5 anos
        }

        // Evolução/saldo/cartões: no mínimo 6 meses para o gráfico fazer sentido
        $chartStart = $start->diffInMonths($end) < 5 ? $end->subMonthsNoOverflow(5)->startOfMonth() : $start;

        $data = [
            'rangeLabel' => $start->format('d/m/Y').' a '.$end->format('d/m/Y'),
            'exportQuery' => ['inicio' => $start->toDateString(), 'fim' => $end->toDateString(), 'regime' => $this->regime],
        ];

        $data += match ($this->tab) {
            'despesas', 'receitas' => $this->categoryData($reports->byCategory($this->tab === 'despesas' ? 'expense' : 'income', $start, $end, $this->regime)),
            'evolucao' => $this->evolutionData($reports->monthly($chartStart, $end)),
            'saldo' => $this->balanceData($reports->balanceHistory($chartStart, $end->min(today()))),
            default => $this->cardsData($reports->invoicesByCard($chartStart, $end)),
        };

        return view('livewire.reports.index', $data);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function categoryData(array $rows): array
    {
        return [
            'rows' => $rows,
            'total' => (int) array_sum(array_column($rows, 'total')),
            'chart' => $rows ? [
                'type' => 'doughnut',
                'data' => ['labels' => array_column($rows, 'name'), 'datasets' => [[
                    'data' => array_column($rows, 'total'), 'backgroundColor' => array_column($rows, 'color'), 'borderWidth' => 0,
                ]]],
            ] : null,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $months
     * @return array<string, mixed>
     */
    private function evolutionData(array $months): array
    {
        $income = array_column($months, 'income');
        $expense = array_column($months, 'expense');

        return [
            'months' => $months,
            'sumIncome' => array_sum($income),
            'sumExpense' => array_sum($expense),
            'avgExpense' => $months ? intdiv((int) array_sum($expense), count($months)) : 0,
            'chart' => [
                'type' => 'bar',
                'data' => ['labels' => array_map(fn ($m) => ucfirst($m['month']->locale('pt_BR')->translatedFormat('M/y')), $months), 'datasets' => [
                    ['label' => 'Receitas', 'data' => $income, 'backgroundColor' => 'var(--income)', 'borderRadius' => 4],
                    ['label' => 'Despesas', 'data' => $expense, 'backgroundColor' => 'var(--expense)', 'borderRadius' => 4],
                ]],
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $months
     * @return array<string, mixed>
     */
    private function balanceData(array $months): array
    {
        return [
            'months' => $months,
            'chart' => [
                'type' => 'line',
                'legend' => false,
                'data' => ['labels' => array_map(fn ($m) => ucfirst($m['month']->locale('pt_BR')->translatedFormat('M/y')), $months), 'datasets' => [[
                    'label' => 'Saldo geral', 'data' => array_column($months, 'balance'),
                    'borderColor' => 'var(--brand)', 'backgroundColor' => 'var(--brand-soft)', 'fill' => true, 'tension' => 0.3, 'pointRadius' => 3,
                ]]],
            ],
        ];
    }

    /**
     * @param  array{months: list<string>, cards: list<array{name: string, color: string, totals: list<int>}>}  $data
     * @return array<string, mixed>
     */
    private function cardsData(array $data): array
    {
        return [
            'cardRows' => $data['cards'],
            'cardMonths' => $data['months'],
            'chart' => $data['cards'] ? [
                'type' => 'bar',
                'stacked' => true,
                'data' => [
                    'labels' => array_map(fn ($k) => ucfirst(Month::parse($k)->locale('pt_BR')->translatedFormat('M/y')), $data['months']),
                    'datasets' => array_map(fn ($c) => ['label' => $c['name'], 'data' => $c['totals'], 'backgroundColor' => $c['color'], 'borderRadius' => 3], $data['cards']),
                ],
            ] : null,
        ];
    }
}
