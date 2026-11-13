<div>
    <x-ui.app-bar title="Planejamento" large>
        <x-slot:actions><x-ui.privacy-toggle /></x-slot:actions>
    </x-ui.app-bar>

    <div class="space-y-4 px-4 pt-2">
        <div>
            <h2 class="text-lg font-semibold">Previsão de saldo</h2>
            <p class="text-sm text-muted">Saldo geral ao fim de cada mês, somando receitas e despesas previstas, fixos, parcelas e faturas de cartão.</p>
        </div>

        @if ($firstNegative)
            <div class="flex gap-3 rounded-2xl bg-rose-500/10 p-4 text-sm text-rose-700 dark:text-rose-300">
                @svg('heroicon-o-exclamation-triangle', 'size-5 shrink-0')
                <p>Atenção: o saldo previsto fica negativo em <strong>{{ mb_strtolower($firstNegative['label']) }}</strong> (<x-ui.money :value="$firstNegative['end']" />).</p>
            </div>
        @endif

        {{-- Gráfico de barras simples do saldo final --}}
        <x-ui.card class="p-4">
            <div class="flex h-36 items-center gap-1.5">
                @foreach ($months as $m)
                    @php($h = max(3, round(abs($m['end']) / $max * 50)))
                    <div class="flex h-full flex-1 flex-col" title="{{ $m['label'] }}: {{ \App\Support\Money::format($m['end']) }}">
                        <div class="flex flex-1 items-end">
                            @if ($m['end'] >= 0)
                                <div class="w-full rounded-t-md bg-brand" style="height: {{ $h * 2 }}%"></div>
                            @endif
                        </div>
                        <div class="h-px bg-line"></div>
                        <div class="flex flex-1 items-start">
                            @if ($m['end'] < 0)
                                <div class="w-full rounded-b-md bg-expense" style="height: {{ $h * 2 }}%"></div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="mt-2 flex gap-1.5 text-center text-[10px] text-muted">
                @foreach ($months as $m)
                    <span class="flex-1 truncate">{{ ucfirst($m['month']->locale('pt_BR')->translatedFormat('M')) }}</span>
                @endforeach
            </div>
        </x-ui.card>

        {{-- Mês a mês --}}
        <x-ui.card class="divide-y divide-line overflow-hidden">
            @foreach ($months as $i => $m)
                <details wire:key="fm-{{ $i }}" class="group" @if ($i === 0) open @endif>
                    <summary class="flex cursor-pointer list-none items-center gap-3 px-4 py-3 hover:bg-card-hover">
                        <span class="min-w-0 flex-1">
                            <span class="block font-medium">{{ $m['label'] }}{{ $i === 0 ? ' (atual)' : '' }}</span>
                            <span class="flex flex-wrap gap-x-2 text-xs">
                                <span class="whitespace-nowrap text-income">↑ <x-ui.money :value="$m['income']" /></span>
                                <span class="whitespace-nowrap text-expense">↓ <x-ui.money :value="$m['expense'] + $m['invoices']" /></span>
                            </span>
                        </span>
                        <x-ui.money :value="$m['end']" @class(['font-semibold', 'text-expense' => $m['end'] < 0]) />
                        @svg('heroicon-o-chevron-down', 'size-4 text-muted transition group-open:rotate-180')
                    </summary>
                    <div class="space-y-1.5 bg-canvas/60 px-4 py-3 text-sm">
                        <div class="flex justify-between"><span class="text-muted">{{ $i === 0 ? 'Saldo atual' : 'Saldo no início' }}</span><x-ui.money :value="$m['start']" /></div>
                        <div class="flex justify-between"><span class="text-muted">Receitas a receber</span><x-ui.money :value="$m['income']" class="text-income" /></div>
                        <div class="flex justify-between"><span class="text-muted">Despesas a pagar</span><x-ui.money :value="-$m['expense']" class="text-expense" /></div>
                        <div class="flex justify-between"><span class="text-muted">Faturas de cartão</span><x-ui.money :value="-$m['invoices']" class="text-expense" /></div>
                        <div class="flex justify-between border-t border-line pt-1.5 font-semibold"><span>Saldo no fim do mês</span><x-ui.money :value="$m['end']" /></div>
                        <a href="{{ route('transactions', ['mes' => \App\Support\Month::key($m['month']), 'status' => 'pending']) }}" wire:navigate class="inline-block pt-1 font-medium text-brand">Ver pendentes do mês →</a>
                    </div>
                </details>
            @endforeach
        </x-ui.card>

        <x-ui.card class="divide-y divide-line overflow-hidden">
            <x-ui.list-item icon="arrow-path" icon-class="bg-sky-100 text-sky-600 dark:bg-sky-950 dark:text-sky-300" label="Lançamentos fixos" description="Salário, aluguel, assinaturas" :href="route('recurrences')" />
            <x-ui.list-item icon="chart-pie" icon-class="bg-amber-100 text-amber-600 dark:bg-amber-950 dark:text-amber-300" label="Orçamentos por categoria" badge="Em breve" />
        </x-ui.card>
    </div>
</div>
