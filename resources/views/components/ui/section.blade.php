@props(['title' => null])
<section {{ $attributes->class('px-4') }}>
    @if ($title)
        <h2 class="mb-2 px-1 text-xs font-semibold uppercase tracking-wider text-muted">{{ $title }}</h2>
    @endif
    {{ $slot }}
</section>
