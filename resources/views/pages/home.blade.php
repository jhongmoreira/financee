<x-layouts.app title="Início">
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
                <x-ui.money :value="0" class="mt-1 block text-3xl font-bold" />
                <div class="mt-5 grid grid-cols-2 gap-3">
                    <div class="rounded-2xl bg-white/15 p-3">
                        <p class="flex items-center gap-1 text-xs text-white/80">@svg('heroicon-m-arrow-trending-up', 'size-4') Receitas</p>
                        <x-ui.money :value="0" class="mt-0.5 block font-semibold" />
                    </div>
                    <div class="rounded-2xl bg-white/15 p-3">
                        <p class="flex items-center gap-1 text-xs text-white/80">@svg('heroicon-m-arrow-trending-down', 'size-4') Despesas</p>
                        <x-ui.money :value="0" class="mt-0.5 block font-semibold" />
                    </div>
                </div>
            </div>
        </div>

        <x-ui.section title="Primeiros passos">
            <x-ui.card class="divide-y divide-line overflow-hidden">
                <x-ui.list-item icon="building-library" label="Cadastre suas contas" description="Bancos, carteira e poupança" badge="Em breve" />
                <x-ui.list-item icon="credit-card" icon-class="bg-violet-100 text-violet-600 dark:bg-violet-950 dark:text-violet-300" label="Adicione seus cartões" description="Faturas, limites e parcelas" badge="Em breve" />
                <x-ui.list-item icon="arrow-down-tray" icon-class="bg-sky-100 text-sky-600 dark:bg-sky-950 dark:text-sky-300" label="Instale o app" description="Acesso rápido pela tela inicial"
                    x-data x-show="! $store.install.standalone" @click="$store.install.prompt()" :chevron="true" />
            </x-ui.card>
        </x-ui.section>
    </div>
</x-layouts.app>
