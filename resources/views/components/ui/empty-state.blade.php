@props(['icon' => 'sparkles', 'title', 'description' => null])
<div {{ $attributes->class('flex flex-col items-center px-6 py-10 text-center') }}>
    <span class="mb-4 grid size-14 place-items-center rounded-2xl bg-brand-soft text-brand-strong">@svg('heroicon-o-'.$icon, 'size-7')</span>
    <h3 class="font-semibold">{{ $title }}</h3>
    @if ($description)
        <p class="mt-1 max-w-xs text-sm text-muted">{{ $description }}</p>
    @endif
    @if (! $slot->isEmpty())
        <div class="mt-5">{{ $slot }}</div>
    @endif
</div>
