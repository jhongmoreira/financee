<div>
    <x-ui.app-bar :title="$asset->name" :back="route('investments')">
        <x-slot:actions>
            <x-ui.privacy-toggle />
            <button type="button" wire:click="editAsset" class="tap rounded-full p-2 text-muted hover:bg-card" aria-label="Editar ativo">
                @svg('heroicon-o-pencil-square', 'size-6')
            </button>
        </x-slot:actions>
    </x-ui.app-bar>

    <div class="space-y-4 px-4 pt-2">
        <x-ui.card class="p-5">
            <div class="flex items-center gap-2 text-sm text-muted">
                <span class="size-2.5 rounded-full" style="background-color: {{ $asset->classColor() }}"></span>
                {{ $asset->classLabel() }} · {{ $asset->portfolio?->name }}
                @if ($asset->archived_at) · <span class="text-amber-600">arquivado</span> @endif
            </div>
            <p class="mt-3 text-sm text-muted">Valor atual</p>
            <x-ui.money :value="$asset->current_value" class="block text-3xl font-bold" />
            @if ($asset->current_value_at)
                <p class="text-xs text-muted">atualizado em {{ $asset->current_value_at->format('d/m/Y') }}</p>
            @endif

            <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                <div><span class="block text-xs text-muted">Aplicado (líquido)</span><x-ui.money :value="$stats['invested']" class="font-semibold" /></div>
                <div>
                    <span class="block text-xs text-muted">Rentabilidade</span>
                    <x-ui.money :value="$stats['gain']" :signed="true" @class(['font-semibold', 'text-income' => $stats['gain'] > 0, 'text-expense' => $stats['gain'] < 0]) />
                    @if ($stats['pct'] !== null) <span class="text-xs text-muted">({{ number_format($stats['pct'], 2, ',', '.') }}%)</span> @endif
                </div>
                @if ($stats['cash_income'])
                    <div><span class="block text-xs text-muted">Rendimentos recebidos</span><x-ui.money :value="$stats['cash_income']" class="font-semibold" /></div>
                @endif
                @if ($asset->rate_description)
                    <div><span class="block text-xs text-muted">Taxa</span><span class="font-semibold">{{ $asset->rate_description }}</span></div>
                @endif
                @if ($asset->maturity_date)
                    <div><span class="block text-xs text-muted">Vencimento</span><span class="font-semibold">{{ $asset->maturity_date->format('d/m/Y') }}</span></div>
                @endif
                @if ($asset->ticker)
                    <div><span class="block text-xs text-muted">Código</span><span class="font-semibold">{{ $asset->ticker }}</span></div>
                @endif
            </div>
        </x-ui.card>

        <div class="grid grid-cols-4 gap-2">
            {{-- Atenção: não usar $type como variável do laço (é o tipo do formulário de movimento) --}}
            @foreach (\App\Models\InvestmentMovement::TYPES as $btnType => [$btnLabel, $btnIcon, $btnColor])
                <button type="button" wire:click="newMovement('{{ $btnType }}')" class="tap flex flex-col items-center gap-1.5 rounded-2xl border border-line bg-card py-3 text-xs font-medium">
                    <span class="grid size-9 place-items-center rounded-full text-white" style="background-color: {{ $btnColor }}">@svg('heroicon-o-'.$btnIcon, 'size-5')</span>
                    {{ $btnType === 'valuation' ? 'Atualizar' : $btnLabel }}
                </button>
            @endforeach
        </div>

        <section>
            <h2 class="mb-1.5 px-1 text-xs font-semibold uppercase tracking-wider text-muted">Movimentações</h2>
            <x-ui.card class="divide-y divide-line overflow-hidden">
                @forelse ($movements as $m)
                    @php([$mvLabel, $mvIcon, $mvColor] = \App\Models\InvestmentMovement::TYPES[$m->type])
                    <button type="button" wire:key="mv-{{ $m->id }}" wire:click="editMovement({{ $m->id }})" class="tap flex w-full items-center gap-3 px-4 py-3 text-left hover:bg-card-hover">
                        <x-ui.badge-avatar :color="$mvColor" :icon="$mvIcon" class="size-10" />
                        <span class="min-w-0 flex-1">
                            <span class="block font-medium">{{ $mvLabel }}</span>
                            <span class="block truncate text-sm text-muted">
                                {{ $m->date->format('d/m/Y') }}
                                @if ($m->account) · {{ $m->type === 'contribution' ? 'de' : 'para' }} {{ $m->account->name }} @endif
                                @if ($m->quantity) · {{ rtrim(rtrim(number_format((float) $m->quantity, 8, ',', '.'), '0'), ',') }} un. @endif
                            </span>
                        </span>
                        <x-ui.money :value="$m->amount" @class(['font-semibold', 'text-income' => $m->type === 'income', 'text-muted' => $m->type === 'valuation']) />
                    </button>
                @empty
                    <x-ui.empty-state icon="arrow-trending-up" title="Sem movimentações" description="Registre um aporte para começar." />
                @endforelse
            </x-ui.card>
        </section>
    </div>

    <x-ui.bottom-sheet name="movement-form" :title="($movementId ? 'Editar ' : '').mb_strtolower(\App\Models\InvestmentMovement::TYPES[$type][0])">
        <form wire:submit="saveMovement" class="space-y-4">
            @if ($movementId)
                <div>
                    <p class="mb-1.5 text-sm font-medium">Tipo</p>
                    <div class="grid grid-cols-4 gap-1 rounded-xl bg-canvas p-1 text-xs font-semibold">
                        @foreach (\App\Models\InvestmentMovement::TYPES as $optType => [$optLabel])
                            <label wire:key="mvtype-{{ $optType }}" @class(['tap cursor-pointer rounded-lg py-2 text-center', 'bg-card text-ink shadow-sm' => $type === $optType, 'text-muted' => $type !== $optType])>
                                <input type="radio" wire:model.live="type" value="{{ $optType }}" class="sr-only"> {{ $optType === 'valuation' ? 'Atualização' : $optLabel }}
                            </label>
                        @endforeach
                    </div>
                </div>
            @endif
            <x-ui.money-input :label="match ($type) { 'valuation' => 'Valor atual do investimento', 'income' => 'Valor do rendimento', default => 'Valor' }" name="amount" wire:model="amount"
                :hint="match ($type) {
                    'valuation' => 'Quanto o investimento vale hoje, no total (veja no app da corretora). Substitui o valor atual.',
                    'income' => 'Só o valor recebido (juros, dividendos, JCP), não o total. Para informar o total, use Atualizar.',
                    'contribution' => 'Quanto você aplicou. Soma ao valor do investimento.',
                    default => 'Quanto você resgatou. Diminui o valor do investimento.',
                }" />
            <x-ui.input label="Data" name="date" type="date" wire:model="date" />
            @if ($type !== 'valuation')
                <x-ui.select :label="$type === 'contribution' ? 'Saiu da conta (opcional)' : 'Entrou na conta (opcional)'" name="account_id" wire:model="account_id" :options="$accounts" placeholder="Nenhuma"
                    :hint="$type === 'income' ? 'Sem conta: o rendimento é reinvestido no ativo.' : 'Movimenta o saldo da conta como transferência (não é despesa/receita).'" />
                @if (in_array($asset->class, ['stock', 'fii', 'etf', 'crypto']) && $type !== 'income')
                    <x-ui.input label="Quantidade (opcional)" name="quantity" wire:model="quantity" inputmode="decimal" placeholder="Ex.: 100" />
                @endif
            @endif
            <x-ui.input label="Observação" name="notes" wire:model="notes" maxlength="200" />
            <x-ui.button class="w-full">Salvar</x-ui.button>
            @if ($movementId)
                <x-ui.button type="button" variant="secondary" class="w-full !text-rose-600 dark:!text-rose-400" wire:click="deleteMovement" wire:confirm="Excluir este movimento?">Excluir</x-ui.button>
            @endif
        </form>
    </x-ui.bottom-sheet>

    <x-ui.bottom-sheet name="asset-edit" title="Editar ativo">
        <form wire:submit="saveAsset" class="space-y-4">
            <x-ui.select label="Tipo" name="class" wire:model="class" :options="collect(\App\Models\InvestmentAsset::CLASSES)->map(fn ($c) => $c[0])->all()" />
            <div class="grid grid-cols-[1fr_7rem] gap-3">
                <x-ui.input label="Nome" name="name" wire:model="name" maxlength="80" />
                <x-ui.input label="Código" name="ticker" wire:model="ticker" maxlength="20" />
            </div>
            <div class="grid grid-cols-2 gap-3">
                <x-ui.input label="Rentabilidade" name="rate_description" wire:model="rate_description" maxlength="60" />
                <x-ui.input label="Vencimento" name="maturity_date" type="date" wire:model="maturity_date" />
            </div>
            <x-ui.button class="w-full">Salvar</x-ui.button>
            <div class="grid grid-cols-2 gap-3">
                <x-ui.button type="button" variant="secondary" wire:click="toggleArchive">{{ $asset->archived_at ? 'Reativar' : 'Arquivar' }}</x-ui.button>
                <x-ui.button type="button" variant="secondary" class="!text-rose-600 dark:!text-rose-400" wire:click="deleteAsset"
                    wire:confirm="Excluir “{{ $asset->name }}” e todas as movimentações? Os efeitos nas contas também serão desfeitos.">Excluir</x-ui.button>
            </div>
        </form>
    </x-ui.bottom-sheet>
</div>
