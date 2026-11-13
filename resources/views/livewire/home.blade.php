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
        </x-slot:actions>
    </x-ui.app-bar>

    <div class="space-y-5 pt-2">
        <div class="px-4">
            <div class="rounded-3xl bg-gradient-to-br from-emerald-500 to-emerald-700 p-5 text-white shadow-lg shadow-emerald-900/20">
                <p class="text-sm text-white/80">Saldo geral</p>
                <x-ui.money :value="$total" class="mt-1 block text-3xl font-bold" />
                <p class="mt-1 text-xs text-white/80">Previsto para o fim de {{ mb_strtolower($monthLabel) }}: <x-ui.money :value="$projected" class="font-semibold text-white" /></p>
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
                        <div wire:key="pending-{{ $row['id'] }}"><x-ui.entry-row :row="$row" /></div>
                    @endforeach
                    <x-ui.list-item icon="clock" icon-class="bg-amber-100 text-amber-600 dark:bg-amber-950 dark:text-amber-300" label="Ver todos os pendentes" :href="route('transactions', ['status' => 'pending'])" />
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
