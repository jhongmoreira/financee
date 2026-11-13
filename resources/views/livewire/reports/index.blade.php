<div>
    <x-ui.app-bar title="Relatórios" :back="route('more')">
        <x-slot:actions>
            <x-ui.privacy-toggle />
            <button type="button" x-on:click="$dispatch('open-sheet', 'export')" class="tap rounded-full p-2 text-muted hover:bg-card" aria-label="Exportar">
                @svg('heroicon-o-arrow-down-tray', 'size-6')
            </button>
        </x-slot:actions>
        <x-slot:below>
            <div class="-mb-1 flex gap-2 overflow-x-auto px-4 pb-3 [scrollbar-width:none]">
                @foreach (\App\Livewire\Reports\Index::TABS as $value => $label)
                    <button type="button" wire:click="$set('tab', '{{ $value }}')"
                            @class(['tap shrink-0 rounded-full px-4 py-1.5 text-sm font-semibold', 'bg-brand text-white' => $tab === $value, 'bg-card text-muted ring-1 ring-line' => $tab !== $value])>{{ $label }}</button>
                @endforeach
            </div>
        </x-slot:below>
    </x-ui.app-bar>

    <div class="space-y-4 px-4 pt-2" wire:loading.class="opacity-60">
        {{-- Período --}}
        <x-ui.card class="space-y-3 p-3">
            <div class="grid grid-cols-2 gap-2">
                <x-ui.select name="preset" wire:model.live="preset" :options="\App\Livewire\Reports\Index::PRESETS" aria-label="Período" />
                @if (in_array($tab, ['despesas', 'receitas']))
                    <x-ui.select name="regime" wire:model.live="regime" :options="['competencia' => 'Data do lançamento', 'caixa' => 'Data do pagamento']" aria-label="Regime" />
                @endif
            </div>
            @if ($preset === 'custom')
                <div class="grid grid-cols-2 gap-2">
                    <x-ui.input name="start" type="date" wire:model.live="start" aria-label="Início" />
                    <x-ui.input name="end" type="date" wire:model.live="end" aria-label="Fim" />
                </div>
            @endif
            <p class="px-1 text-xs text-muted">{{ $rangeLabel }}@if (in_array($tab, ['evolucao', 'saldo', 'cartoes'])) · gráficos mostram pelo menos 6 meses @endif</p>
        </x-ui.card>

        @if (in_array($tab, ['despesas', 'receitas']))
            @if ($chart)
                <x-ui.card class="p-4">
                    <div class="relative mx-auto size-48">
                        <x-ui.chart :config="$chart" height="size-48" />
                        <div class="pointer-events-none absolute inset-0 grid place-items-center text-center">
                            <span><span class="block text-xs text-muted">Total</span><x-ui.money :value="$total" class="text-base font-bold" /></span>
                        </div>
                    </div>
                </x-ui.card>
                <x-ui.card class="divide-y divide-line overflow-hidden">
                    @foreach ($rows as $i => $row)
                        <details wire:key="rc-{{ $tab }}-{{ $i }}" class="group">
                            <summary class="flex cursor-pointer list-none items-center gap-3 px-4 py-3 hover:bg-card-hover">
                                <x-ui.badge-avatar :color="$row['color']" :icon="$row['icon']" class="size-9" />
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate font-medium">{{ $row['name'] }}</span>
                                    <span class="block text-xs text-muted">
                                        {{ number_format($row['pct'], 1, ',', '.') }}% do total
                                        @if ($row['change'] !== null)
                                            · <span @class(['font-medium',
                                                'text-expense' => ($tab === 'despesas') === ($row['change'] > 0),
                                                'text-income' => ($tab === 'despesas') === ($row['change'] < 0)])>{{ $row['change'] > 0 ? '▲' : '▼' }} {{ number_format(abs($row['change']), 1, ',', '.') }}%</span> vs. período anterior
                                        @endif
                                    </span>
                                </span>
                                <x-ui.money :value="$row['total']" class="font-semibold" />
                            </summary>
                            <div class="space-y-1 bg-canvas/60 px-4 py-3 text-sm">
                                @forelse ($row['children'] as $child)
                                    <div class="flex justify-between"><span class="text-muted">{{ $child['name'] }}</span><x-ui.money :value="$child['total']" /></div>
                                @empty
                                    <p class="text-muted">Sem subcategorias.</p>
                                @endforelse
                                @if ($row['id'])
                                    <a href="{{ route('transactions', ['categoria' => $row['id'], 'tipo' => $tab === 'despesas' ? 'expense' : 'income']) }}" wire:navigate class="inline-block pt-1 font-medium text-brand">Ver lançamentos →</a>
                                @endif
                            </div>
                        </details>
                    @endforeach
                </x-ui.card>
            @else
                <x-ui.card><x-ui.empty-state icon="chart-pie" title="Sem {{ $tab }} no período" /></x-ui.card>
            @endif
        @elseif ($tab === 'evolucao')
            <x-ui.card class="grid grid-cols-3 gap-2 p-4 text-center">
                <div><p class="text-xs text-muted">Receitas</p><x-ui.money :value="$sumIncome" class="text-sm font-semibold text-income" /></div>
                <div><p class="text-xs text-muted">Despesas</p><x-ui.money :value="$sumExpense" class="text-sm font-semibold text-expense" /></div>
                <div><p class="text-xs text-muted">Média/mês</p><x-ui.money :value="$avgExpense" class="text-sm font-semibold" /></div>
            </x-ui.card>
            <x-ui.card class="p-4"><x-ui.chart :config="$chart" height="h-60" /></x-ui.card>
            <x-ui.card class="divide-y divide-line overflow-hidden text-sm">
                @foreach (array_reverse($months) as $m)
                    <div class="flex items-center gap-3 px-4 py-2.5">
                        <span class="flex-1 font-medium">{{ $m['label'] }}</span>
                        <x-ui.money :value="$m['income']" class="w-24 text-right text-income" />
                        <x-ui.money :value="$m['expense']" class="w-24 text-right text-expense" />
                        <x-ui.money :value="$m['balance']" :signed="true" @class(['w-24 text-right font-semibold', 'text-expense' => $m['balance'] < 0]) />
                    </div>
                @endforeach
            </x-ui.card>
        @elseif ($tab === 'saldo')
            <x-ui.card class="p-4"><x-ui.chart :config="$chart" height="h-72" /></x-ui.card>

            {{-- Tabela mês × conta (rola para o lado no celular) --}}
            <x-ui.card class="overflow-x-auto">
                <table class="w-full min-w-max text-sm">
                    <thead>
                        <tr class="border-b border-line text-xs text-muted">
                            <th class="sticky left-0 bg-card px-4 py-2.5 text-left font-semibold">Mês</th>
                            <th class="px-3 py-2.5 text-right font-semibold text-ink">Total</th>
                            @foreach ($balanceAccounts as $acc)
                                <th class="px-3 py-2.5 text-right font-semibold">
                                    <span class="inline-flex items-center gap-1.5"><span class="size-2 rounded-full" style="background-color: {{ $acc['color'] }}"></span>{{ $acc['name'] }}</span>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach (array_reverse($months) as $m)
                            <tr wire:key="bal-{{ $m['label'] }}">
                                <td class="sticky left-0 bg-card px-4 py-2.5 font-medium">{{ $m['label'] }}</td>
                                <td class="px-3 py-2.5 text-right"><x-ui.money :value="$m['balance']" @class(['font-semibold', 'text-expense' => $m['balance'] < 0]) /></td>
                                @foreach ($balanceAccounts as $acc)
                                    @php($v = $m['accounts'][$acc['id']] ?? 0)
                                    <td class="px-3 py-2.5 text-right"><x-ui.money :value="$v" @class(['text-muted' => $v === 0, 'text-expense' => $v < 0]) /></td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-ui.card>
            <p class="px-1 text-xs text-muted">Saldo efetivado no fim de cada mês (o mês atual mostra o saldo de hoje). O total soma as contas marcadas "incluir no saldo geral"; investimentos ficam de fora.</p>
        @elseif ($tab === 'cartoes')
            @if ($chart)
                <x-ui.card class="p-4"><x-ui.chart :config="$chart" height="h-60" /></x-ui.card>
                <x-ui.card class="divide-y divide-line overflow-hidden text-sm">
                    @foreach ($cardRows as $card)
                        <div class="flex items-center gap-3 px-4 py-3">
                            <span class="size-3 rounded-full" style="background-color: {{ $card['color'] }}"></span>
                            <span class="flex-1 font-medium">{{ $card['name'] }}</span>
                            <span class="text-right text-xs text-muted">média/mês<br><x-ui.money :value="intdiv(array_sum($card['totals']), max(1, count($card['totals'])))" class="text-sm font-semibold text-ink" /></span>
                        </div>
                    @endforeach
                </x-ui.card>
            @else
                <x-ui.card><x-ui.empty-state icon="credit-card" title="Nenhuma fatura no período" /></x-ui.card>
            @endif
        @endif
    </div>

    <x-ui.bottom-sheet name="export" title="Exportar">
        <div class="space-y-4">
            <p class="text-sm text-muted">Período: <strong class="text-ink">{{ $rangeLabel }}</strong> (o mesmo escolhido na tela)</p>

            <div>
                <div class="mb-2 flex items-center justify-between">
                    <p class="text-sm font-semibold">O que incluir no PDF</p>
                    <button type="button" class="text-sm font-medium text-brand" wire:click="toggleAllSections">
                        {{ count($sections) === count(\App\Livewire\Reports\Index::PDF_SECTIONS) ? 'Desmarcar todas' : 'Marcar todas' }}
                    </button>
                </div>
                <div class="divide-y divide-line rounded-2xl border border-line">
                    @foreach (\App\Livewire\Reports\Index::PDF_SECTIONS as $key => $label)
                        <label wire:key="sec-{{ $key }}" class="flex cursor-pointer items-center gap-3 px-4 py-3">
                            <input type="checkbox" value="{{ $key }}" wire:model.live="sections" class="size-5 rounded accent-emerald-600">
                            <span class="text-sm">{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            @if ($sections)
                <a href="{{ route('export.pdf', $pdfQuery) }}" class="tap flex w-full items-center justify-center gap-2 rounded-xl bg-brand py-3 font-semibold text-white hover:bg-brand-strong">
                    @svg('heroicon-o-document-arrow-down', 'size-5') Gerar PDF
                </a>
            @else
                <p class="rounded-xl bg-canvas px-4 py-3 text-center text-sm text-muted">Marque pelo menos uma seção para gerar o PDF.</p>
            @endif

            <a href="{{ route('export.csv', $exportQuery) }}" class="tap flex items-center gap-3 rounded-2xl border border-line p-4 hover:bg-card-hover">
                <x-ui.badge-avatar color="#16a34a" icon="document-text" class="size-10" />
                <span><span class="block font-medium">Planilha (CSV)</span><span class="block text-sm text-muted">Lista de lançamentos do período, abre no Excel</span></span>
            </a>
        </div>
    </x-ui.bottom-sheet>
</div>
