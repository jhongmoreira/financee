<div>
    <x-ui.app-bar>
        <x-slot:leading>
            <a href="{{ route('profile') }}" wire:navigate class="flex items-center gap-3">
                <x-ui.avatar :user="auth()->user()" class="size-9 text-sm" />
                <span class="leading-tight">
                    <span class="block text-xs text-muted">{{ now()->hour < 12 ? 'Bom dia' : (now()->hour < 18 ? 'Boa tarde' : 'Boa noite') }},</span>
                    <span class="block font-semibold">{{ auth()->user()->firstName() }}</span>
                </span>
            </a>
        </x-slot:leading>
        <x-slot:actions>
            <x-ui.privacy-toggle />
            <a href="{{ route('notifications') }}" wire:navigate class="tap relative rounded-full p-2 text-muted hover:bg-card" aria-label="Notificações">
                @svg('heroicon-o-bell', 'size-6')
                @if ($unread)
                    <span class="absolute right-1 top-1 grid min-w-4 place-items-center rounded-full bg-rose-500 px-1 text-[10px] font-bold text-white">{{ $unread > 9 ? '9+' : $unread }}</span>
                @endif
            </a>
        </x-slot:actions>
    </x-ui.app-bar>

    <div class="space-y-5 pt-2">
        <div class="px-4">
            <div class="rounded-3xl bg-gradient-to-br from-emerald-500 to-emerald-700 p-5 text-white shadow-lg shadow-emerald-900/20">
                <button type="button" wire:click="toggleBalanceView" class="tap -mx-1 flex items-center gap-1.5 rounded-lg px-1 text-sm text-white/80 hover:text-white"
                        aria-label="{{ $balanceView === 'geral' ? 'Mostrar patrimônio total' : 'Mostrar saldo geral' }}">
                    {{ $balanceView === 'geral' ? 'Saldo geral' : 'Patrimônio total' }}
                    @svg('heroicon-m-arrows-right-left', 'size-4')
                </button>
                @if ($balanceView === 'geral')
                    <x-ui.money :value="$total" class="mt-1 block text-3xl font-bold" />
                    <p class="mt-1 text-xs text-white/80">Previsto para o fim de {{ mb_strtolower($monthLabel) }}: <x-ui.money :value="$projected" class="font-semibold text-white" /></p>
                @else
                    <x-ui.money :value="$total + $otherAccountsTotal + $investedTotal" class="mt-1 block text-3xl font-bold" />
                    <div class="mt-2 space-y-0.5 text-xs text-white/85">
                        <div class="flex justify-between gap-3"><span>Contas no saldo geral</span><x-ui.money :value="$total" class="font-semibold text-white" /></div>
                        @if ($otherAccountsTotal !== 0)
                            <div class="flex justify-between gap-3"><span>Outras contas</span><x-ui.money :value="$otherAccountsTotal" class="font-semibold text-white" /></div>
                        @endif
                        <div class="flex justify-between gap-3"><span>Investimentos</span><x-ui.money :value="$investedTotal" class="font-semibold text-white" /></div>
                    </div>
                @endif
                <div class="mt-5 grid grid-cols-2 gap-3">
                    <a href="{{ route('transactions', ['tipo' => 'income']) }}" wire:navigate class="tap rounded-2xl bg-white/15 p-3">
                        <p class="flex items-center gap-1 text-xs text-white/80">@svg('heroicon-m-arrow-trending-up', 'size-4') Receitas do mês</p>
                        <x-ui.money :value="$totals['income']" class="mt-0.5 block font-semibold" />
                    </a>
                    <a href="{{ route('transactions', ['tipo' => 'expense']) }}" wire:navigate class="tap rounded-2xl bg-white/15 p-3">
                        <p class="flex items-center gap-1 text-xs text-white/80">@svg('heroicon-m-arrow-trending-down', 'size-4') Despesas do mês</p>
                        <x-ui.money :value="$totals['expense']" class="mt-0.5 block font-semibold" />
                    </a>
                </div>
            </div>
        </div>

        @if ($pending->isNotEmpty())
            <x-ui.section title="Contas a pagar e receber">
                <x-ui.card class="divide-y divide-line overflow-hidden">
                    @foreach ($pending as $row)
                        <div wire:key="pending-{{ $row['kind'] }}-{{ $row['id'] }}"><x-ui.entry-row :row="$row" :show-pay="$row['kind'] === 'transaction'" /></div>
                    @endforeach
                    <x-ui.list-item icon="clock" icon-class="bg-amber-100 text-amber-600 dark:bg-amber-950 dark:text-amber-300" label="Ver todos os pendentes" :href="route('transactions', ['status' => 'pending'])" />
                </x-ui.card>
            </x-ui.section>
        @endif

        @if ($categoryChart)
            <x-ui.section title="Despesas de {{ mb_strtolower($monthLabel) }}">
                <x-ui.card class="flex items-center gap-4 p-4">
                    <div class="relative size-32 shrink-0">
                        <x-ui.chart :config="$categoryChart" height="size-32" />
                        <div class="pointer-events-none absolute inset-0 grid place-items-center text-center">
                            <span><span class="block text-[10px] text-muted">Total</span><x-ui.money :value="$categoryChart['total']" class="text-xs font-semibold" /></span>
                        </div>
                    </div>
                    <div class="min-w-0 flex-1 space-y-1.5 text-sm">
                        @foreach ($categoryChart['legendItems'] as [$label, $color, $value])
                            <div class="flex items-center gap-2">
                                <span class="size-2.5 shrink-0 rounded-full" style="background-color: {{ $color }}"></span>
                                <span class="min-w-0 flex-1 truncate">{{ $label }}</span>
                                <x-ui.money :value="$value" class="text-xs text-muted" />
                            </div>
                        @endforeach
                    </div>
                </x-ui.card>
            </x-ui.section>
        @endif

        @if ($budgets->isNotEmpty())
            <x-ui.section title="Orçamentos">
                <x-ui.card class="divide-y divide-line overflow-hidden">
                    @foreach ($budgets as $row)
                        <a href="{{ route('planning') }}" wire:navigate wire:key="hb-{{ $row['category']->id }}" class="tap block px-4 py-3 hover:bg-card-hover">
                            <span class="flex items-center justify-between gap-3 text-sm">
                                <span class="truncate font-medium">{{ $row['category']->name }}</span>
                                <span class="text-xs text-muted"><x-ui.money :value="$row['spent']" /> / <x-ui.money :value="$row['budget']" /></span>
                            </span>
                            <x-ui.progress :pct="$row['pct']" class="mt-2" />
                        </a>
                    @endforeach
                </x-ui.card>
            </x-ui.section>
        @endif

        <x-ui.section title="Contas">
            <x-ui.card class="divide-y divide-line overflow-hidden">
                @foreach ($accounts as $account)
                    <a href="{{ route('transactions', ['conta' => $account->id]) }}" wire:navigate wire:key="home-acc-{{ $account->id }}" class="tap flex items-center gap-3 px-4 py-3 hover:bg-card-hover">
                        @if ($account->institution && $account->institution->type !== 'other')
                            <x-ui.badge-avatar :color="$account->color" :text="$account->institution->short" class="size-9" />
                        @else
                            <x-ui.badge-avatar :color="$account->color" :icon="$account->typeIcon()" class="size-9" />
                        @endif
                        <span class="min-w-0 flex-1 truncate font-medium">{{ $account->name }}</span>
                        <x-ui.money :value="$account->balance()" @class(['font-semibold', 'text-expense' => $account->balance() < 0]) />
                    </a>
                @endforeach
                <x-ui.list-item icon="cog-6-tooth" label="Gerenciar contas" :href="route('accounts')" />
            </x-ui.card>
        </x-ui.section>

        @if ($cards->isNotEmpty())
            <x-ui.section title="Cartões">
                <x-ui.card class="divide-y divide-line overflow-hidden">
                    @foreach ($cards as $card)
                        @php($inv = $currentInvoices[$card->id])
                        <a href="{{ route('cards.invoice', $card) }}" wire:navigate wire:key="home-card-{{ $card->id }}" class="tap flex items-center gap-3 px-4 py-3 hover:bg-card-hover">
                            <x-ui.badge-avatar :color="$card->color" icon="credit-card" class="size-9" />
                            <span class="min-w-0 flex-1">
                                <span class="block truncate font-medium">{{ $card->name }}</span>
                                <span class="block truncate text-sm text-muted">Fatura {{ $inv->shortLabel() }} · vence {{ $inv->due_date->format('d/m') }}</span>
                            </span>
                            <x-ui.money :value="$invoiceSummaries[$inv->id]['total']" class="font-semibold" />
                        </a>
                    @endforeach
                </x-ui.card>
            </x-ui.section>
        @endif

        @if ($hasInvestments)
            <div class="px-4">
                <a href="{{ route('investments') }}" wire:navigate class="tap flex items-center gap-3 rounded-2xl border border-line bg-card p-4 hover:bg-card-hover">
                    <x-ui.badge-avatar color="#4f46e5" icon="arrow-trending-up" class="size-10" />
                    <span class="min-w-0 flex-1">
                        <span class="block font-medium">Investimentos</span>
                        <span class="block text-xs text-muted">Separado do saldo geral</span>
                    </span>
                    <x-ui.money :value="$investedTotal" class="font-semibold" />
                    @svg('heroicon-o-chevron-right', 'size-5 text-muted')
                </a>
            </div>
        @endif

        @if (array_sum($historyChart['data']['datasets'][0]['data']) + array_sum($historyChart['data']['datasets'][1]['data']) > 0)
            <x-ui.section title="Receitas × despesas">
                <x-ui.card class="p-4">
                    <x-ui.chart :config="$historyChart" height="h-48" />
                </x-ui.card>
            </x-ui.section>
        @endif

        @if ($recent->isNotEmpty())
            <x-ui.section title="Últimos lançamentos">
                <x-ui.card class="divide-y divide-line overflow-hidden">
                    @foreach ($recent as $row)
                        <div wire:key="recent-{{ $row['id'] }}"><x-ui.entry-row :row="$row" :show-pay="false" /></div>
                    @endforeach
                    <x-ui.list-item icon="list-bullet" label="Ver extrato completo" :href="route('transactions')" />
                </x-ui.card>
            </x-ui.section>
        @endif

        @if ($cardsCount === 0 || $recent->isEmpty())
            <x-ui.section title="Primeiros passos">
                <x-ui.card class="divide-y divide-line overflow-hidden">
                    @if ($recent->isEmpty())
                        <x-ui.list-item icon="plus" label="Registre seu primeiro lançamento" description="Toque no + para lançar uma despesa ou receita"
                            x-data @click="$dispatch('open-sheet', 'entry-form'); $dispatch('open-entry', { kind: 'expense' })" :chevron="true" />
                    @endif
                    @if ($cardsCount === 0)
                        <x-ui.list-item icon="credit-card" icon-class="bg-violet-100 text-violet-600 dark:bg-violet-950 dark:text-violet-300" label="Adicione seus cartões" description="Faturas, limites e parcelas" :href="route('cards')" />
                    @endif
                    <x-ui.list-item icon="arrow-down-tray" icon-class="bg-sky-100 text-sky-600 dark:bg-sky-950 dark:text-sky-300" label="Instale o app" description="Acesso rápido pela tela inicial"
                        x-data x-show="! $store.install.standalone" @click="$store.install.prompt()" :chevron="true" />
                </x-ui.card>
            </x-ui.section>
        @endif
    </div>
</div>
