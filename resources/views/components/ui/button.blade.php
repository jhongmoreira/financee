@props(['variant' => 'primary', 'href' => null, 'type' => 'submit'])
@php
    $classes = [
        'tap inline-flex items-center justify-center gap-2 rounded-xl px-5 py-3 font-semibold disabled:opacity-60',
        'bg-brand text-white shadow-sm hover:bg-brand-strong' => $variant === 'primary',
        'border border-line bg-card text-ink hover:bg-card-hover' => $variant === 'secondary',
        'bg-rose-600 text-white hover:bg-rose-700' => $variant === 'danger',
        'text-brand hover:bg-brand-soft' => $variant === 'ghost',
    ];
@endphp
@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" wire:loading.attr="disabled" {{ $attributes->class($classes) }}>{{ $slot }}</button>
@endif
