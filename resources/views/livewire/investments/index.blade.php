<div>
    <x-ui.app-bar title="Investimentos" :back="route('more')">
        <x-slot:actions>
            <x-ui.privacy-toggle />
            <button type="button" wire:click="createPortfolio" class="tap rounded-full p-2 text-brand hover:bg-card" aria-label="Nova carteira">
                @svg('heroicon-o-plus-circle', 'size-7')
            </button>
        </x-slot:actions>
    </x-ui.app-bar>

    <div class="space-y-5 px-4 pt-2">
        <div class="rounded-3xl bg-gradient-to-br from-indigo-500 to-indigo-800 p-5 text-white shadow-lg shadow-indigo-900/20">
            <p class="text-sm text-white/80">Patrimônio investido</p>
            <x-ui.money :value="$total['current']" class="mt-1 block text-3xl font-bold" />
            <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                <div class="rounded-2xl bg-white/15 p-3">
                    <p class="text-xs text-white/75">Aplicado (líquido)</p>
                    <x-ui.money :value="$total['invested']" class="font-semibold" />
                </div>
                <div class="rounded-2xl bg-white/15 p-3">
                    <p class="text-xs text-white/75">Rentabilidade</p>
                    <x-ui.money :value="$total['gain']" :signed="true" class="font-semibold" />
                    @if ($total['pct'] !== null)
                        <span class="text-xs text-white/80">({{ number_format($total['pct'], 2, ',', '.') }}%)</span>
                    @endif
                </div>
            </div>
            <p class="mt-3 text-xs text-white/70">@svg('heroicon-m-information-circle', 'inline size-4') Separado do saldo das contas: não entra no saldo geral nem nos relatórios de despesas.</p>
        </div>

        @if ($byClass->isNotEmpty() && $total['current'] > 0)
            <x-ui.card class="p-4">
                <p class="mb-3 text-sm font-semibold">Distribuição por classe</p>
                <div class="flex h-3 overflow-hidden rounded-full">
                    @foreach ($byClass as $c)
                        <div style="width: {{ $c['value'] / $total['current'] * 100 }}%; background-color: {{ $c['color'] }}"></div>
                    @endforeach
                </div>
                <div class="mt-3 space-y-1.5 text-sm">
                    @foreach ($byClass as $c)
                        <div class="flex items-center gap-2">
                            <span class="size-2.5 rounded-full" style="background-color: {{ $c['color'] }}"></span>
                            <span class="flex-1">{{ $c['label'] }}</span>
                            <span class="text-muted">{{ number_format($c['value'] / $total['current'] * 100, 1, ',', '.') }}%</span>
                            <x-ui.money :value="$c['value']" class="w-28 text-right font-medium" />
                        </div>
                    @endforeach
                </div>
            </x-ui.card>
        @endif

        @forelse ($portfolios as $portfolio)
            @php($pStats = $portfolioStats[$portfolio->id])
            <section wire:key="pf-{{ $portfolio->id }}">
                <div class="mb-2 flex items-center gap-2 px-1">
                    <span class="size-3 rounded-full" style="background-color: {{ $portfolio->color }}"></span>
                    <h2 class="flex-1 font-semibold">{{ $portfolio->name }}</h2>
                    <x-ui.money :value="$pStats['current']" class="text-sm font-semibold" />
                    <button type="button" wire:click="editPortfolio({{ $portfolio->id }})" class="tap rounded-full p-1.5 text-muted hover:bg-card" aria-label="Editar carteira {{ $portfolio->name }}">
                        @svg('heroicon-o-pencil-square', 'size-5')
                    </button>
                </div>
                <x-ui.card class="divide-y divide-line overflow-hidden">
                    @foreach ($portfolio->assets as $asset)
                        @php($s = $stats[$asset->id])
                        <a href="{{ route('investments.asset', $asset) }}" wire:navigate wire:key="as-{{ $asset->id }}" class="tap flex items-center gap-3 px-4 py-3 hover:bg-card-hover">
                            <span class="grid size-10 shrink-0 place-items-center rounded-full text-[11px] font-bold text-white" style="background-color: {{ $asset->classColor() }}">
                                {{ $asset->ticker ? mb_substr($asset->ticker, 0, 4) : mb_strtoupper(mb_substr($asset->name, 0, 2)) }}
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate font-medium">{{ $asset->name }}</span>
                                <span class="block truncate text-sm text-muted">{{ $asset->classLabel() }}{{ $asset->rate_description ? ' · '.$asset->rate_description : '' }}</span>
                            </span>
                            <span class="text-right">
                                <x-ui.money :value="$asset->current_value" class="block font-semibold" />
                                @if ($s['pct'] !== null)
                                    <span @class(['text-xs font-medium', 'text-income' => $s['gain'] >= 0, 'text-expense' => $s['gain'] < 0])>{{ $s['gain'] >= 0 ? '+' : '' }}{{ number_format($s['pct'], 2, ',', '.') }}%</span>
                                @endif
                            </span>
                        </a>
                    @endforeach
                    <x-ui.list-item icon="plus" icon-class="bg-indigo-100 text-indigo-600 dark:bg-indigo-950 dark:text-indigo-300" label="Adicionar ativo" wire:click="createAsset({{ $portfolio->id }})" />
                </x-ui.card>
            </section>
        @empty
            <x-ui.card>
                <x-ui.empty-state icon="arrow-trending-up" title="Nenhuma carteira ainda"
                    description="Crie uma carteira (ex.: sua corretora) e cadastre ações, FIIs, CDBs, Tesouro e outros. Você atualiza os valores quando quiser.">
                    <x-ui.button type="button" wire:click="createPortfolio">@svg('heroicon-o-plus', 'size-5') Nova carteira</x-ui.button>
                </x-ui.empty-state>
            </x-ui.card>
        @endforelse
    </div>

    <x-ui.bottom-sheet name="portfolio-form" :title="$portfolioId ? 'Editar carteira' : 'Nova carteira'">
        <form wire:submit="savePortfolio" class="space-y-4">
            <x-ui.select label="Corretora / banco" name="institution_id" wire:model.live="institution_id" :options="$institutions" placeholder="Nenhuma" />
            <x-ui.input label="Nome da carteira" name="name" wire:model="name" maxlength="60" placeholder="Ex.: XP, Reserva de emergência" />
            <x-ui.color-picker wire:model="color" />
            <x-ui.button class="w-full">Salvar</x-ui.button>
            @if ($editingPortfolio)
                <x-ui.button type="button" variant="secondary" class="w-full !text-rose-600 dark:!text-rose-400" wire:click="deletePortfolio"
                    wire:confirm="Excluir a carteira “{{ $editingPortfolio->name }}” com todos os ativos e movimentos?">Excluir carteira</x-ui.button>
            @endif
        </form>
    </x-ui.bottom-sheet>

    <x-ui.bottom-sheet name="asset-form" title="Novo ativo">
        <form wire:submit="saveAsset" class="space-y-4">
            <x-ui.select label="Carteira" name="asset_portfolio_id" wire:model="asset_portfolio_id" :options="$portfolios->pluck('name', 'id')->all()" placeholder="Selecione" />
            <x-ui.select label="Tipo" name="asset_class" wire:model.live="asset_class" :options="collect(\App\Models\InvestmentAsset::CLASSES)->map(fn ($c) => $c[0])->all()" />
            <div class="grid grid-cols-[1fr_7rem] gap-3">
                <x-ui.input label="Nome" name="asset_name" wire:model="asset_name" maxlength="80" placeholder="Ex.: CDB Banco X 2028" />
                <x-ui.input label="Código" name="asset_ticker" wire:model="asset_ticker" maxlength="20" placeholder="PETR4" />
            </div>
            @if (in_array($asset_class, ['cdb', 'lci_lca', 'treasury', 'fixed_income', 'pension', 'fund']))
                <div class="grid grid-cols-2 gap-3">
                    <x-ui.input label="Rentabilidade" name="asset_rate" wire:model="asset_rate" maxlength="60" placeholder="110% CDI" />
                    <x-ui.input label="Vencimento" name="asset_maturity" type="date" wire:model="asset_maturity" />
                </div>
            @endif
            <x-ui.money-input label="Valor aplicado (opcional)" name="asset_amount" wire:model="asset_amount" />
            <div class="grid grid-cols-2 gap-3">
                <x-ui.input label="Data" name="asset_date" type="date" wire:model="asset_date" />
                <x-ui.select label="Saiu da conta" name="asset_account_id" wire:model="asset_account_id" :options="$accounts" placeholder="Nenhuma" />
            </div>
            <p class="text-xs text-muted">Se escolher uma conta, o valor sai do saldo dela (como transferência, não como despesa).</p>
            <x-ui.button class="w-full">Criar ativo</x-ui.button>
        </form>
    </x-ui.bottom-sheet>
</div>
