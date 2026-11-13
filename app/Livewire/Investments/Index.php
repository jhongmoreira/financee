<?php

namespace App\Livewire\Investments;

use App\Models\Account;
use App\Models\Institution;
use App\Models\InvestmentAsset;
use App\Models\InvestmentMovement;
use App\Models\InvestmentPortfolio;
use App\Services\InvestmentService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Investimentos')]
class Index extends Component
{
    // Carteira
    #[Locked]
    public ?int $portfolioId = null;

    public ?string $institution_id = null;

    public string $name = '';

    public string $color = '#4f46e5';

    // Novo ativo
    public ?string $asset_portfolio_id = null;

    public string $asset_name = '';

    public string $asset_ticker = '';

    public string $asset_class = 'cdb';

    public string $asset_rate = '';

    public ?string $asset_maturity = null;

    public int $asset_amount = 0;

    public string $asset_date = '';

    public ?string $asset_account_id = null;

    public function createPortfolio(): void
    {
        $this->resetValidation();
        $this->reset('portfolioId', 'institution_id', 'name', 'color');
        $this->dispatch('open-sheet', 'portfolio-form');
    }

    public function editPortfolio(int $id): void
    {
        $p = InvestmentPortfolio::findOrFail($id);
        $this->resetValidation();
        $this->portfolioId = $p->id;
        $this->institution_id = $p->institution_id ? (string) $p->institution_id : null;
        $this->name = $p->name;
        $this->color = $p->color;
        $this->dispatch('open-sheet', 'portfolio-form');
    }

    public function updatedInstitutionId(?string $value): void
    {
        $institution = $value ? Institution::find($value) : null;
        if ($institution && $institution->type !== 'other') {
            $this->name = $this->name ?: $institution->name;
            $this->color = $institution->color;
        }
    }

    public function savePortfolio(): void
    {
        $data = $this->validate([
            'institution_id' => ['nullable', 'integer', 'exists:institutions,id'],
            'name' => ['required', 'string', 'max:60'],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        $this->portfolioId
            ? InvestmentPortfolio::findOrFail($this->portfolioId)->update($data)
            : InvestmentPortfolio::create($data);

        $this->dispatch('close-sheet', 'portfolio-form');
        $this->dispatch('toast', message: 'Carteira salva.');
    }

    public function deletePortfolio(): void
    {
        InvestmentPortfolio::findOrFail($this->portfolioId)->delete();
        $this->dispatch('close-sheet', 'portfolio-form');
        $this->dispatch('toast', message: 'Carteira excluída.');
    }

    public function createAsset(?int $portfolioId = null): void
    {
        $this->resetValidation();
        $this->reset('asset_name', 'asset_ticker', 'asset_class', 'asset_rate', 'asset_maturity', 'asset_amount', 'asset_account_id');
        $this->asset_portfolio_id = (string) ($portfolioId ?? InvestmentPortfolio::query()->active()->value('id') ?? '') ?: null;
        $this->asset_date = today()->toDateString();
        $this->dispatch('open-sheet', 'asset-form');
    }

    public function saveAsset(InvestmentService $service): void
    {
        $this->validate([
            'asset_portfolio_id' => ['required', 'integer', Rule::exists('investment_portfolios', 'id')->where('user_id', Auth::id())],
            'asset_name' => ['required', 'string', 'max:80'],
            'asset_ticker' => ['nullable', 'string', 'max:20'],
            'asset_class' => ['required', Rule::in(array_keys(InvestmentAsset::CLASSES))],
            'asset_rate' => ['nullable', 'string', 'max:60'],
            'asset_maturity' => ['nullable', 'date_format:Y-m-d'],
            'asset_amount' => ['required', 'integer', 'min:0', 'max:100000000000'],
            'asset_date' => ['required', 'date_format:Y-m-d'],
            'asset_account_id' => ['nullable', 'integer', Rule::exists('accounts', 'id')->where('user_id', Auth::id())],
        ], attributes: ['asset_portfolio_id' => 'carteira', 'asset_name' => 'nome', 'asset_amount' => 'valor aplicado', 'asset_account_id' => 'conta']);

        $asset = DB::transaction(function () use ($service) {
            $asset = InvestmentAsset::create([
                'portfolio_id' => $this->asset_portfolio_id,
                'name' => trim($this->asset_name),
                'ticker' => mb_strtoupper(trim($this->asset_ticker)) ?: null,
                'class' => $this->asset_class,
                'rate_description' => trim($this->asset_rate) ?: null,
                'maturity_date' => $this->asset_maturity ?: null,
            ]);

            if ($this->asset_amount > 0) {
                InvestmentMovement::create([
                    'asset_id' => $asset->id, 'type' => 'contribution', 'amount' => $this->asset_amount,
                    'date' => $this->asset_date, 'account_id' => $this->asset_account_id ?: null,
                ]);
                $service->recalculate($asset);
            }

            return $asset;
        });

        $this->dispatch('close-sheet', 'asset-form');
        $this->redirectRoute('investments.asset', $asset, navigate: true);
    }

    public function render(InvestmentService $service): View
    {
        $portfolios = InvestmentPortfolio::query()->active()->with(['assets' => fn ($q) => $q->whereNull('archived_at'), 'institution'])->orderBy('name')->get();
        $assets = $portfolios->flatMap->assets;
        $stats = $service->stats($assets);

        $byClass = $assets->groupBy('class')->map(fn ($group, $class) => [
            'label' => InvestmentAsset::CLASSES[$class][0] ?? 'Outros',
            'color' => InvestmentAsset::CLASSES[$class][1] ?? '#6b7280',
            'value' => (int) $group->sum('current_value'),
        ])->sortByDesc('value')->values();

        return view('livewire.investments.index', [
            'portfolios' => $portfolios,
            'stats' => $stats,
            'total' => $service->sum($stats),
            'portfolioStats' => $portfolios->mapWithKeys(fn (InvestmentPortfolio $p) => [
                $p->id => $service->sum(array_intersect_key($stats, array_flip($p->assets->pluck('id')->all()))),
            ]),
            'byClass' => $byClass,
            'institutions' => Institution::options('broker'),
            'accounts' => Account::query()->active()->ordered()->pluck('name', 'id')->all(),
            'editingPortfolio' => $this->portfolioId ? InvestmentPortfolio::find($this->portfolioId) : null,
        ]);
    }
}
