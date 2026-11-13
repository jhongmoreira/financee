<?php

namespace App\Livewire\Investments;

use App\Models\Account;
use App\Models\InvestmentAsset;
use App\Models\InvestmentMovement;
use App\Services\InvestmentService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Asset extends Component
{
    #[Locked]
    public int $assetId;

    // Movimento
    #[Locked]
    public ?int $movementId = null;

    public string $type = 'contribution';

    public int $amount = 0;

    public string $date = '';

    public ?string $account_id = null;

    public string $quantity = '';

    public string $notes = '';

    // Dados do ativo
    public string $name = '';

    public string $ticker = '';

    public string $class = 'other';

    public string $rate_description = '';

    public ?string $maturity_date = null;

    public function mount(InvestmentAsset $asset): void
    {
        $this->assetId = $asset->id;
    }

    public function newMovement(string $type): void
    {
        $this->resetValidation();
        $this->reset('movementId', 'amount', 'account_id', 'quantity', 'notes');
        $this->type = array_key_exists($type, InvestmentMovement::TYPES) ? $type : 'contribution';
        $this->date = today()->toDateString();

        if ($this->type === 'valuation') {
            $this->amount = $this->asset()->current_value;
        }

        $this->dispatch('open-sheet', 'movement-form');
    }

    public function editMovement(int $id): void
    {
        $m = InvestmentMovement::where('asset_id', $this->assetId)->findOrFail($id);
        $this->resetValidation();
        $this->movementId = $m->id;
        $this->type = $m->type;
        $this->amount = $m->amount;
        $this->date = $m->date->toDateString();
        $this->account_id = $m->account_id ? (string) $m->account_id : null;
        $this->quantity = $m->quantity !== null ? rtrim(rtrim((string) $m->quantity, '0'), '.') : '';
        $this->notes = (string) $m->notes;
        $this->dispatch('open-sheet', 'movement-form');
    }

    public function saveMovement(InvestmentService $service): void
    {
        $data = $this->validate([
            'type' => ['required', Rule::in(array_keys(InvestmentMovement::TYPES))],
            'amount' => ['required', 'integer', $this->type === 'valuation' ? 'min:0' : 'min:1', 'max:100000000000'],
            'date' => ['required', 'date_format:Y-m-d'],
            'account_id' => ['nullable', 'integer', Rule::exists('accounts', 'id')->where('user_id', Auth::id())],
            'quantity' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:200'],
        ], attributes: ['amount' => 'valor', 'account_id' => 'conta']);

        $data['account_id'] = $this->type === 'valuation' ? null : ($data['account_id'] ?: null);
        $data['quantity'] = $data['quantity'] !== '' ? $data['quantity'] : null;
        $data['notes'] = $data['notes'] ?: null;
        $data['asset_id'] = $this->assetId;

        $this->movementId
            ? InvestmentMovement::where('asset_id', $this->assetId)->findOrFail($this->movementId)->update($data)
            : InvestmentMovement::create($data);

        $service->recalculate($this->asset());

        $this->dispatch('close-sheet', 'movement-form');
        $this->dispatch('toast', message: 'Movimentação salva.');
    }

    public function deleteMovement(InvestmentService $service): void
    {
        InvestmentMovement::where('asset_id', $this->assetId)->findOrFail($this->movementId)->delete();
        $service->recalculate($this->asset());

        $this->dispatch('close-sheet', 'movement-form');
        $this->dispatch('toast', message: 'Movimento excluído.');
    }

    public function editAsset(): void
    {
        $a = $this->asset();
        $this->resetValidation();
        $this->name = $a->name;
        $this->ticker = (string) $a->ticker;
        $this->class = $a->class;
        $this->rate_description = (string) $a->rate_description;
        $this->maturity_date = $a->maturity_date?->toDateString();
        $this->dispatch('open-sheet', 'asset-edit');
    }

    public function saveAsset(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:80'],
            'ticker' => ['nullable', 'string', 'max:20'],
            'class' => ['required', Rule::in(array_keys(InvestmentAsset::CLASSES))],
            'rate_description' => ['nullable', 'string', 'max:60'],
            'maturity_date' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $data['ticker'] = mb_strtoupper(trim((string) $data['ticker'])) ?: null;
        $data['rate_description'] = $data['rate_description'] ?: null;
        $data['maturity_date'] = $data['maturity_date'] ?: null;

        $this->asset()->update($data);
        $this->dispatch('close-sheet', 'asset-edit');
        $this->dispatch('toast', message: 'Ativo atualizado.');
    }

    public function toggleArchive(): void
    {
        $asset = $this->asset();
        $asset->update(['archived_at' => $asset->archived_at ? null : now()]);
        $this->dispatch('close-sheet', 'asset-edit');
        $this->dispatch('toast', message: $asset->archived_at ? 'Ativo arquivado (fora dos totais).' : 'Ativo reativado.');
    }

    public function deleteAsset(): void
    {
        $this->asset()->delete();
        $this->redirectRoute('investments', navigate: true);
    }

    public function render(InvestmentService $service): View
    {
        $asset = $this->asset();
        $movements = $asset->movements()->with('account')->orderByDesc('date')->orderByDesc('id')->get();

        return view('livewire.investments.asset', [
            'asset' => $asset,
            'stats' => $service->stats(collect([$asset]))[$asset->id],
            'movements' => $movements,
            'accounts' => Account::query()->active()->ordered()->pluck('name', 'id')->all(),
        ])->title($asset->name);
    }

    private function asset(): InvestmentAsset
    {
        return InvestmentAsset::with('portfolio')->findOrFail($this->assetId);
    }
}
