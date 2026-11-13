@php
    $accounts = \App\Models\Account::active()->ordered()->with('institution')->get();
    $total = $accounts->where('include_in_total', true)->sum(fn ($a) => $a->balance());
    $cardsCount = \App\Models\CreditCard::active()->count();
@endphp
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
                <x-ui.money :value="$total" class="mt-1 block text-3xl font-bold" />
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

        <x-ui.section title="Contas">
            <x-ui.card class="divide-y divide-line overflow-hidden">
                @foreach ($accounts as $account)
                    <a href="{{ route('accounts') }}" wire:navigate wire:key="home-acc-{{ $account->id }}" class="tap flex items-center gap-3 px-4 py-3 hover:bg-card-hover">
                        @if ($account->institution && $account->institution->type !== 'other')
                            <x-ui.badge-avatar :color="$account->color" :text="$account->institution->short" class="size-9" />
                        @else
                            <x-ui.badge-avatar :color="$account->color" :icon="$account->typeIcon()" class="size-9" />
                        @endif
                        <span class="min-w-0 flex-1 truncate font-medium">{{ $account->name }}</span>
                        <x-ui.money :value="$account->balance()" @class(['font-semibold', 'text-expense' => $account->balance() < 0]) />
                    </a>
                @endforeach
                <x-ui.list-item icon="plus" label="Gerenciar contas" :href="route('accounts')" />
            </x-ui.card>
        </x-ui.section>

        <x-ui.section title="Primeiros passos">
            <x-ui.card class="divide-y divide-line overflow-hidden">
                @if ($cardsCount === 0)
                    <x-ui.list-item icon="credit-card" icon-class="bg-violet-100 text-violet-600 dark:bg-violet-950 dark:text-violet-300" label="Adicione seus cartões" description="Faturas, limites e parcelas" :href="route('cards')" />
                @endif
                <x-ui.list-item icon="tag" icon-class="bg-amber-100 text-amber-600 dark:bg-amber-950 dark:text-amber-300" label="Revise suas categorias" description="Já criamos as principais para você" :href="route('categories')" />
                <x-ui.list-item icon="arrow-down-tray" icon-class="bg-sky-100 text-sky-600 dark:bg-sky-950 dark:text-sky-300" label="Instale o app" description="Acesso rápido pela tela inicial"
                    x-data x-show="! $store.install.standalone" @click="$store.install.prompt()" :chevron="true" />
            </x-ui.card>
        </x-ui.section>
    </div>
</x-layouts.app>
