<div x-data="{ startX: null }"
     x-on:touchstart.passive="startX = $event.touches[0].clientX"
     x-on:touchend="if (startX !== null) { const dx = $event.changedTouches[0].clientX - startX; if (Math.abs(dx) > 80 && !document.body.style.overflow) { dx > 0 ? $wire.previousMonth() : $wire.nextMonth() } startX = null }">
    <x-ui.app-bar title="Lançamentos" large>
        <x-slot:actions>
            <x-ui.privacy-toggle />
            <button type="button" wire:click="toggleOrder" class="tap rounded-full p-2 text-muted hover:bg-card"
                    aria-label="{{ $order === 'asc' ? 'Mostrar mais recentes primeiro' : 'Mostrar em ordem do início do mês' }}"
                    title="{{ $order === 'asc' ? 'Ordem: do início do mês' : 'Ordem: mais recentes primeiro' }}">
                @svg($order === 'asc' ? 'heroicon-o-bars-arrow-down' : 'heroicon-o-bars-arrow-up', 'size-6')
            </button>
            <button type="button" x-on:click="$dispatch('open-sheet', 'filters')" class="tap relative rounded-full p-2 text-muted hover:bg-card" aria-label="Filtros">
                @svg('heroicon-o-funnel', 'size-6')
                @if ($activeFilters)
                    <span class="absolute right-1 top-1 grid size-4 place-items-center rounded-full bg-brand text-[10px] font-bold text-white">{{ $activeFilters }}</span>
                @endif
            </button>
        </x-slot:actions>
        <x-slot:below>
            <x-ui.month-switcher :label="$monthLabel" />
        </x-slot:below>
    </x-ui.app-bar>

    <div class="space-y-4 pt-1">
        {{-- Resumo do mês --}}
        <div class="px-4">
            <x-ui.card class="p-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="flex items-center gap-1 text-xs text-muted">@svg('heroicon-m-arrow-trending-up', 'size-4 text-income') Receitas</p>
                        <x-ui.money :value="$totals['income']" class="block text-lg font-semibold text-income" />
                    </div>
                    <div>
                        <p class="flex items-center gap-1 text-xs text-muted">@svg('heroicon-m-arrow-trending-down', 'size-4 text-expense') Despesas</p>
                        <x-ui.money :value="$totals['expense']" class="block text-lg font-semibold text-expense" />
                    </div>
                </div>
                <div class="mt-3 flex items-center justify-between border-t border-line pt-3 text-sm">
                    <span class="text-muted">Balanço do mês</span>
                    <x-ui.money :value="$totals['balance']" :signed="true" @class(['font-semibold', 'text-income' => $totals['balance'] > 0, 'text-expense' => $totals['balance'] < 0]) />
                </div>
            </x-ui.card>
        </div>

        @if ($activeFilters)
            <div class="flex items-center justify-between px-5 text-sm">
                <span class="text-muted">{{ $activeFilters }} {{ $activeFilters === 1 ? 'filtro ativo' : 'filtros ativos' }}</span>
                <button type="button" wire:click="clearFilters" class="font-medium text-brand">Limpar</button>
            </div>
        @endif

        <div class="space-y-4 px-4" wire:loading.class="opacity-60" wire:target="previousMonth,nextMonth,type,account,category,status,search">
            @forelse ($groups as $day => $rows)
                <section wire:key="day-{{ $day }}">
                    <h2 class="mb-1.5 px-1 text-xs font-semibold uppercase tracking-wider text-muted">{{ \App\Support\Month::dayLabel(\Carbon\CarbonImmutable::parse($day)) }}</h2>
                    <x-ui.card class="divide-y divide-line overflow-hidden">
                        @foreach ($rows as $row)
                            <div wire:key="{{ $row['kind'] }}-{{ $row['id'] }}">
                                <x-ui.entry-row :row="$row" />
                            </div>
                        @endforeach
                    </x-ui.card>
                </section>
            @empty
                <x-ui.card>
                    <x-ui.empty-state icon="arrows-right-left" :title="$activeFilters ? 'Nada encontrado' : 'Nenhum lançamento em '.$monthLabel"
                        :description="$activeFilters ? 'Tente ajustar os filtros.' : 'Toque no + para registrar uma despesa, receita ou transferência.'" />
                </x-ui.card>
            @endforelse
        </div>
    </div>

    {{-- Filtros --}}
    <x-ui.bottom-sheet name="filters" title="Filtros">
        <div class="space-y-4">
            <x-ui.input name="search" wire:model.live.debounce.400ms="search" placeholder="Buscar pela descrição" type="search" aria-label="Buscar" />
            <x-ui.select label="Tipo" name="type" wire:model.live="type" :options="['expense' => 'Despesas', 'income' => 'Receitas', 'transfer' => 'Transferências']" placeholder="Todos" />
            <x-ui.select label="Situação" name="status" wire:model.live="status" :options="['paid' => 'Efetivados', 'pending' => 'Pendentes']" placeholder="Todos" />
            <x-ui.select label="Conta" name="account" wire:model.live="account" :options="$accounts" placeholder="Todas" />
            <div>
                <label for="f-category" class="mb-1.5 block text-sm font-medium">Categoria</label>
                <div class="relative">
                    <select id="f-category" wire:model.live="category" class="block w-full appearance-none rounded-xl border border-line bg-card py-3 pl-4 pr-10 outline-none focus:border-brand">
                        <option value="">Todas</option>
                        @foreach ($categories->groupBy('type') as $catType => $roots)
                            <optgroup label="{{ \App\Models\Category::TYPES[$catType] }}">
                                @foreach ($roots as $root)
                                    <option value="{{ $root->id }}">{{ $root->name }}</option>
                                    @foreach ($root->children as $child)
                                        <option value="{{ $child->id }}">&nbsp;&nbsp;↳ {{ $child->name }}</option>
                                    @endforeach
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                    @svg('heroicon-m-chevron-up-down', 'pointer-events-none absolute right-3 top-1/2 size-5 -translate-y-1/2 text-muted')
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3 pt-1">
                <x-ui.button type="button" variant="secondary" wire:click="clearFilters">Limpar</x-ui.button>
                <x-ui.button type="button" x-on:click="close()">Ver resultados</x-ui.button>
            </div>
        </div>
    </x-ui.bottom-sheet>
</div>
