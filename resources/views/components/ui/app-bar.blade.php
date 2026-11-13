@props(['title' => null, 'back' => null, 'large' => false])
{{--
    Barra superior fixa. Slots: $leading (esquerda), $actions (direita), $below (ex.: seletor de mês).
    large: título grande (telas principais das abas), na mesma linha das ações.
--}}
<header class="sticky top-0 z-20 bg-canvas/90 pt-safe backdrop-blur">
    <div @class(['flex items-center gap-2 px-4', 'h-14' => ! $large, 'h-16 pt-2' => $large])>
        @if ($back)
            <a href="{{ $back }}" wire:navigate class="tap -ml-2 rounded-full p-2 hover:bg-card" aria-label="Voltar">
                @svg('heroicon-o-chevron-left', 'size-6')
            </a>
        @endif
        <div class="min-w-0 flex-1">
            {{ $leading ?? '' }}
            @if ($title)
                <h1 @class(['truncate', 'text-lg font-semibold' => ! $large, 'text-2xl font-bold tracking-tight' => $large])>{{ $title }}</h1>
            @endif
        </div>
        @isset($actions)
            <div class="flex items-center gap-1">{{ $actions }}</div>
        @endisset
    </div>
    {{ $below ?? '' }}
</header>
