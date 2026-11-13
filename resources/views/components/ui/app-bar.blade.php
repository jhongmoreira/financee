@props(['title' => null, 'back' => null, 'large' => false])
{{-- Barra superior fixa. Slots: $actions (direita), $below (ex.: seletor de mês) --}}
<header class="sticky top-0 z-20 bg-canvas/90 pt-safe backdrop-blur">
    <div class="flex h-14 items-center gap-2 px-4">
        @if ($back)
            <a href="{{ $back }}" wire:navigate class="tap -ml-2 rounded-full p-2 hover:bg-card" aria-label="Voltar">
                @svg('heroicon-o-chevron-left', 'size-6')
            </a>
        @endif
        <div class="min-w-0 flex-1">
            {{ $leading ?? '' }}
            @if ($title && ! $large)
                <h1 class="truncate text-lg font-semibold">{{ $title }}</h1>
            @endif
        </div>
        @isset($actions)
            <div class="flex items-center gap-1">{{ $actions }}</div>
        @endisset
    </div>
    @if ($title && $large)
        <h1 class="px-4 pb-2 text-3xl font-bold tracking-tight">{{ $title }}</h1>
    @endif
    {{ $below ?? '' }}
</header>
