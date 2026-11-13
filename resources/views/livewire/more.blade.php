<div>
    <x-ui.app-bar title="Mais" large />

    <div class="space-y-6 pt-2">
        <div class="px-4">
            <a href="{{ route('profile') }}" wire:navigate class="tap flex items-center gap-4 rounded-2xl border border-line bg-card p-4 hover:bg-card-hover">
                <x-ui.avatar :user="$user" class="size-14 text-lg" />
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-lg font-semibold">{{ $user->name }}</span>
                    <span class="block truncate text-sm text-muted">{{ $user->email }}</span>
                </span>
                @svg('heroicon-o-chevron-right', 'size-5 text-muted')
            </a>
        </div>

        <x-ui.section title="Cadastros">
            <x-ui.card class="divide-y divide-line overflow-hidden">
                <x-ui.list-item icon="building-library" label="Contas" description="Bancos, poupança e carteira" :href="route('accounts')" />
                <x-ui.list-item icon="credit-card" icon-class="bg-violet-100 text-violet-600 dark:bg-violet-950 dark:text-violet-300" label="Cartões de crédito" description="Limites, fechamento e vencimento" :href="route('cards')" />
                <x-ui.list-item icon="tag" icon-class="bg-amber-100 text-amber-600 dark:bg-amber-950 dark:text-amber-300" label="Categorias" description="Receitas e despesas" :href="route('categories')" />
                <x-ui.list-item icon="arrow-path" icon-class="bg-sky-100 text-sky-600 dark:bg-sky-950 dark:text-sky-300" label="Lançamentos fixos" description="Salário, aluguel, assinaturas" :href="route('recurrences')" />
            </x-ui.card>
        </x-ui.section>

        <x-ui.section title="Patrimônio e análises">
            <x-ui.card class="divide-y divide-line overflow-hidden">
                <x-ui.list-item icon="arrow-trending-up" icon-class="bg-indigo-100 text-indigo-600 dark:bg-indigo-950 dark:text-indigo-300" label="Investimentos" description="Ações, FIIs, renda fixa (saldo separado)" :href="route('investments')" />
                <x-ui.list-item icon="chart-bar" icon-class="bg-pink-100 text-pink-600 dark:bg-pink-950 dark:text-pink-300" label="Relatórios" badge="Em breve" />
            </x-ui.card>
        </x-ui.section>

        <x-ui.section title="Aparência">
            <x-ui.card class="p-1.5">
                <div class="grid grid-cols-3 gap-1" role="radiogroup" aria-label="Tema">
                    @foreach (['light' => ['Claro', 'sun'], 'dark' => ['Escuro', 'moon'], 'system' => ['Sistema', 'device-phone-mobile']] as $value => [$label, $icon])
                        <label wire:key="theme-{{ $value }}"
                               class="tap flex cursor-pointer flex-col items-center gap-1 rounded-xl py-3 text-sm font-medium text-muted has-[:checked]:bg-brand-soft has-[:checked]:text-brand-strong">
                            <input type="radio" wire:model.live="theme" value="{{ $value }}" class="sr-only"
                                   x-on:change="window.applyTheme('{{ $value }}')">
                            @svg('heroicon-o-'.$icon, 'size-6')
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
            </x-ui.card>
        </x-ui.section>

        <x-ui.section title="Aplicativo">
            <x-ui.card class="divide-y divide-line overflow-hidden" x-data>
                <x-ui.list-item icon="arrow-down-tray" label="Instalar o app" description="Acesso rápido pela tela inicial"
                    x-show="! $store.install.standalone" @click="$store.install.prompt()" :chevron="true" />
                <x-ui.list-item icon="bell" icon-class="bg-orange-100 text-orange-600 dark:bg-orange-950 dark:text-orange-300" label="Notificações" badge="Em breve" />
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="tap flex w-full items-center gap-3 px-4 py-3 text-left font-medium text-rose-600 hover:bg-card-hover dark:text-rose-400">
                        <span class="grid size-9 place-items-center rounded-full bg-rose-100 dark:bg-rose-950">@svg('heroicon-o-arrow-right-start-on-rectangle', 'size-5')</span>
                        Sair
                    </button>
                </form>
            </x-ui.card>
        </x-ui.section>

        <p class="pb-4 text-center text-xs text-muted">{{ config('app.name') }} · {{ trim(@file_get_contents(base_path('RELEASE')) ?: 'dev') }}</p>
    </div>
</div>
