@props(['color' => '#6b7280', 'icon' => null, 'text' => null])
{{-- Círculo colorido com ícone ou sigla (contas, cartões, categorias, instituições) --}}
<span {{ $attributes->class('grid shrink-0 place-items-center rounded-full font-bold text-white') }} style="background-color: {{ $color }}">
    @if ($icon)
        @svg('heroicon-o-'.\App\Support\Palette::safeIcon($icon), 'size-5')
    @else
        <span class="text-xs">{{ $text }}</span>
    @endif
</span>
