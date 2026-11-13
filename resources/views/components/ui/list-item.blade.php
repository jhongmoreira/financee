@props([
    'href' => null,
    'icon' => null,
    'iconClass' => 'bg-brand-soft text-brand-strong',
    'label',
    'description' => null,
    'chevron' => null,
    'badge' => null,
    'navigate' => true,
])
@php
    $tag = $href ? 'a' : ($attributes->has('wire:click') || $attributes->has('@click') || $attributes->has('x-on:click') ? 'button' : 'div');
    $chevron ??= (bool) $href;
@endphp
<{{ $tag }}
    @if ($href) href="{{ $href }}" @if ($navigate) wire:navigate @endif @endif
    @if ($tag === 'button') type="button" @endif
    {{ $attributes->class([
        'flex w-full items-center gap-3 px-4 py-3 text-left',
        'tap hover:bg-card-hover' => $tag !== 'div',
    ]) }}>
    @if ($icon)
        <span class="grid size-9 shrink-0 place-items-center rounded-full {{ $iconClass }}">@svg('heroicon-o-'.$icon, 'size-5')</span>
    @endif
    <span class="min-w-0 flex-1">
        <span class="block truncate font-medium">{{ $label }}</span>
        @if ($description)
            <span class="block truncate text-sm text-muted">{{ $description }}</span>
        @endif
    </span>
    @if ($badge)
        <span class="shrink-0 rounded-full bg-canvas px-2 py-0.5 text-xs font-medium text-muted">{{ $badge }}</span>
    @endif
    {{ $slot }}
    @if ($chevron)
        @svg('heroicon-o-chevron-right', 'size-5 shrink-0 text-muted')
    @endif
</{{ $tag }}>
