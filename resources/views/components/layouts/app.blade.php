@props(['title' => null])
@php
    $nav = [
        ['route' => 'home', 'label' => 'Início', 'icon' => 'home', 'exact' => true],
        ['route' => 'transactions', 'label' => 'Lançamentos', 'icon' => 'arrows-right-left'],
        ['route' => 'planning', 'label' => 'Planejamento', 'icon' => 'chart-pie'],
        ['route' => 'more', 'label' => 'Mais', 'icon' => 'squares-2x2'],
    ];
    $user = auth()->user();
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <x-layouts.head :title="$title" />
</head>
<body class="min-h-dvh bg-canvas text-ink antialiased">
    <div class="lg:flex">
        {{-- Sidebar (desktop) --}}
        @persist('sidebar')
        <aside class="sticky top-0 hidden h-dvh w-64 shrink-0 flex-col border-r border-line bg-card px-4 py-6 lg:flex">
            <a href="{{ route('home') }}" wire:navigate class="mb-8 flex items-center gap-3 px-2">
                <img src="/icons/logo.svg" alt="" class="size-9 rounded-xl">
                <span class="text-lg font-bold tracking-tight">{{ config('app.name') }}</span>
            </a>

            <button type="button" x-data @click="$dispatch('open-sheet', 'new-entry')"
                    class="tap mb-6 flex items-center justify-center gap-2 rounded-xl bg-brand px-4 py-3 font-semibold text-white shadow-sm hover:bg-brand-strong">
                @svg('heroicon-o-plus', 'size-5') Novo lançamento
            </button>

            <nav class="flex flex-col gap-1">
                @foreach ($nav as $item)
                    <a href="{{ route($item['route']) }}" wire:navigate
                       @if ($item['exact'] ?? false) wire:current.exact="!bg-brand-soft !text-brand-strong" @else wire:current="!bg-brand-soft !text-brand-strong" @endif
                       class="flex items-center gap-3 rounded-xl px-3 py-2.5 font-medium text-muted hover:bg-card-hover hover:text-ink">
                        @svg('heroicon-o-'.$item['icon'], 'size-5')
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </nav>

            <a href="{{ route('profile') }}" wire:navigate class="mt-auto flex items-center gap-3 rounded-xl px-2 py-2 hover:bg-card-hover">
                <x-ui.avatar :user="$user" class="size-9" />
                <span class="min-w-0">
                    <span class="block truncate text-sm font-semibold">{{ $user->name }}</span>
                    <span class="block truncate text-xs text-muted">{{ $user->email }}</span>
                </span>
            </a>
        </aside>
        @endpersist

        <div class="min-w-0 flex-1">
            <main class="mx-auto w-full max-w-3xl pb-32 lg:px-6 lg:pb-10">
                {{ $slot }}
            </main>
        </div>
    </div>

    {{-- Bottom navigation (mobile) --}}
    @persist('bottom-nav')
    <nav class="fixed inset-x-0 bottom-0 z-30 border-t border-line bg-card/95 pb-safe backdrop-blur lg:hidden">
        <div class="mx-auto grid h-16 max-w-md grid-cols-5 items-center">
            @foreach ($nav as $i => $item)
                @if ($i === 2)
                    <div class="flex justify-center">
                        <button type="button" x-data @click="$dispatch('open-sheet', 'new-entry')" aria-label="Novo lançamento"
                                class="tap -mt-7 grid size-14 place-items-center rounded-full bg-brand text-white shadow-lg shadow-emerald-900/30 ring-4 ring-canvas">
                            @svg('heroicon-o-plus', 'size-7')
                        </button>
                    </div>
                @endif
                <a href="{{ route($item['route']) }}" wire:navigate
                   @if ($item['exact'] ?? false) wire:current.exact="!text-brand" @else wire:current="!text-brand" @endif
                   class="tap flex flex-col items-center gap-0.5 text-[11px] font-medium text-muted">
                    @svg('heroicon-o-'.$item['icon'], 'size-6')
                    <span>{{ $item['label'] }}</span>
                </a>
            @endforeach
        </div>
    </nav>

    {{-- Novo lançamento (implementado na Etapa 3) --}}
    <x-ui.bottom-sheet name="new-entry" title="Novo lançamento">
        <div class="grid grid-cols-2 gap-3">
            @foreach ([
                ['Despesa', 'arrow-trending-down', 'text-expense'],
                ['Receita', 'arrow-trending-up', 'text-income'],
                ['Despesa no cartão', 'credit-card', 'text-violet-500'],
                ['Transferência', 'arrows-right-left', 'text-sky-500'],
            ] as [$label, $icon, $color])
                <button type="button" @click="$store.toast.show('Disponível em breve.', 'info'); close()"
                        class="tap flex flex-col items-start gap-3 rounded-2xl border border-line bg-canvas p-4 text-left">
                    <span class="grid size-10 place-items-center rounded-full bg-card {{ $color }}">@svg('heroicon-o-'.$icon, 'size-5')</span>
                    <span class="font-semibold">{{ $label }}</span>
                </button>
            @endforeach
        </div>
    </x-ui.bottom-sheet>

    {{-- Instruções de instalação no iPhone --}}
    <x-ui.bottom-sheet name="ios-install" title="Instalar no iPhone">
        <ol class="space-y-4 text-sm">
            <li class="flex items-center gap-3"><span class="grid size-8 shrink-0 place-items-center rounded-full bg-brand-soft font-bold text-brand-strong">1</span> Toque em @svg('heroicon-o-arrow-up-on-square', 'inline size-5 text-sky-500') <strong>Compartilhar</strong> na barra do Safari.</li>
            <li class="flex items-center gap-3"><span class="grid size-8 shrink-0 place-items-center rounded-full bg-brand-soft font-bold text-brand-strong">2</span> Escolha <strong>Adicionar à Tela de Início</strong>.</li>
            <li class="flex items-center gap-3"><span class="grid size-8 shrink-0 place-items-center rounded-full bg-brand-soft font-bold text-brand-strong">3</span> Toque em <strong>Adicionar</strong>. Pronto!</li>
        </ol>
    </x-ui.bottom-sheet>
    @endpersist

    <x-ui.toasts />
</body>
</html>
