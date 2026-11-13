<div>
    <x-ui.app-bar title="Planejamento" large>
        <x-slot:actions><x-ui.privacy-toggle /></x-slot:actions>
        <x-slot:below>
            <div class="px-4 pb-3">
                <div class="grid grid-cols-2 gap-1 rounded-xl bg-card p-1 ring-1 ring-line">
                    @foreach (['orcamentos' => 'Orçamentos', 'previsao' => 'Previsão'] as $value => $label)
                        <button type="button" wire:click="$set('tab', '{{ $value }}')"
                                @class(['tap rounded-lg py-2 text-sm font-semibold', 'bg-brand-soft text-brand-strong' => $tab === $value, 'text-muted' => $tab !== $value])>{{ $label }}</button>
                    @endforeach
                </div>
            </div>
            @if ($tab === 'orcamentos')
                <x-ui.month-switcher :label="$monthLabel" />
            @endif
        </x-slot:below>
    </x-ui.app-bar>

    @if ($tab === 'orcamentos')
        <div class="space-y-4 px-4 pt-1" wire:loading.class="opacity-60" wire:target="previousMonth,nextMonth">
            @if ($rows->isNotEmpty())
                <x-ui.card class="p-4">
                    <div class="flex items-end justify-between gap-3">
                        <div>
                            <p class="text-xs text-muted">Gasto no mês</p>
                            <x-ui.money :value="$totals['spent']" class="block text-2xl font-bold" />
                            <p class="text-xs text-muted">de <x-ui.money :value="$totals['budget']" /> orçados</p>
                        </div>
                        <div class="text-right">
                            <p class="text-xs text-muted">{{ $totals['remaining'] >= 0 ? 'Disponível' : 'Acima do orçamento' }}</p>
                            <x-ui.money :value="abs($totals['remaining'])" @class(['block text-lg font-semibold', 'text-income' => $totals['remaining'] >= 0, 'text-expense' => $totals['remaining'] < 0]) />
                            @if ($totals['per_day'] > 0)
                                <p class="text-xs text-muted"><x-ui.money :value="$totals['per_day']" /> por dia</p>
                            @endif
                        </div>
                    </div>
                    <x-ui.progress :pct="$totals['pct']" class="mt-3" />
                </x-ui.card>

                <x-ui.card class="divide-y divide-line overflow-hidden">
                    @foreach ($rows as $row)
                        <button type="button" wire:key="bud-{{ $row['category']->id }}" wire:click="editBudget({{ $row['category']->id }})" class="tap block w-full px-4 py-3 text-left hover:bg-card-hover">
                            <span class="flex items-center gap-3">
                                <x-ui.badge-avatar :color="$row['category']->color" :icon="$row['category']->icon" class="size-9" />
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate font-medium">{{ $row['category']->name }}</span>
                                    <span class="block text-xs text-muted"><x-ui.money :value="$row['spent']" /> de <x-ui.money :value="$row['budget']" />{{ $row['is_default'] ? '' : ' · só este mês' }}</span>
                                </span>
                                <span @class(['text-sm font-semibold', 'text-expense' => $row['pct'] > 100, 'text-amber-600 dark:text-amber-400' => $row['pct'] >= 80 && $row['pct'] <= 100, 'text-income' => $row['pct'] < 80])>{{ $row['pct'] }}%</span>
                            </span>
                            <x-ui.progress :pct="$row['pct']" class="mt-2" />
                        </button>
                    @endforeach
                </x-ui.card>
            @else
                <x-ui.card>
                    <x-ui.empty-state icon="chart-pie" title="Nenhum orçamento em {{ mb_strtolower($monthLabel) }}"
                        description="Defina quanto quer gastar por categoria e acompanhe ao longo do mês. Sugerimos valores pela média dos últimos 3 meses." />
                </x-ui.card>
            @endif

            <div class="grid grid-cols-2 gap-3">
                <x-ui.button type="button" wire:click="editBudget">@svg('heroicon-o-plus', 'size-5') Orçamento</x-ui.button>
                <x-ui.button type="button" variant="secondary" wire:click="copyPrevious">Copiar mês anterior</x-ui.button>
            </div>
        </div>

        <x-ui.bottom-sheet name="budget-form" title="Orçamento da categoria">
            <form wire:submit="saveBudget" class="space-y-4">
                <x-ui.select label="Categoria" name="budget_category_id" wire:model.live="budget_category_id"
                    :options="$categoryOptions" placeholder="Selecione" />
                <x-ui.money-input label="Limite mensal" name="budget_amount" wire:model="budget_amount"
                    hint="Inclui as subcategorias, despesas em conta e no cartão." />
                <div class="grid grid-cols-2 gap-1 rounded-xl bg-canvas p-1 text-xs font-semibold">
                    @foreach (['all' => 'Todos os meses', 'month' => 'Só '.mb_strtolower($monthLabel)] as $value => $label)
                        <label wire:key="bscope-{{ $value }}" @class(['tap cursor-pointer rounded-lg py-2 text-center', 'bg-card text-ink shadow-sm' => $budget_scope === $value, 'text-muted' => $budget_scope !== $value])>
                            <input type="radio" wire:model.live="budget_scope" value="{{ $value }}" class="sr-only"> {{ $label }}
                        </label>
                    @endforeach
                </div>
                <x-ui.button class="w-full">Salvar</x-ui.button>
                @if ($budget_category_id && in_array((int) $budget_category_id, $budgetedIds, true))
                    <x-ui.button type="button" variant="secondary" class="w-full !text-rose-600 dark:!text-rose-400" wire:click="removeBudget" wire:confirm="Remover o orçamento desta categoria?">Remover orçamento</x-ui.button>
                @endif
            </form>
        </x-ui.bottom-sheet>
    @else
        <div class="space-y-4 px-4 pt-1">
            <p class="px-1 text-sm text-muted">Saldo geral ao fim de cada mês, somando receitas e despesas previstas, fixos, parcelas e faturas de cartão.</p>

            @if ($firstNegative)
                <div class="flex gap-3 rounded-2xl bg-rose-500/10 p-4 text-sm text-rose-700 dark:text-rose-300">
                    @svg('heroicon-o-exclamation-triangle', 'size-5 shrink-0')
                    <p>Atenção: o saldo previsto fica negativo em <strong>{{ mb_strtolower($firstNegative['label']) }}</strong> (<x-ui.money :value="$firstNegative['end']" />).</p>
                </div>
            @endif

            <x-ui.card class="p-4">
                <x-ui.chart :config="$chart" height="h-48" />
            </x-ui.card>

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

            <x-ui.card class="overflow-hidden">
                <x-ui.list-item icon="arrow-path" icon-class="bg-sky-100 text-sky-600 dark:bg-sky-950 dark:text-sky-300" label="Lançamentos fixos" description="Salário, aluguel, assinaturas" :href="route('recurrences')" />
            </x-ui.card>
        </div>
    @endif
</div>
