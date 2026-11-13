@props(['label' => null, 'name', 'type' => 'text', 'hint' => null])
@php
    $model = $attributes->wire('model')->value() ?: $name;
    $id = $attributes->get('id', 'f-'.str_replace('.', '-', $model));
@endphp
<div>
    @if ($label)
        <label for="{{ $id }}" class="mb-1.5 block text-sm font-medium">{{ $label }}</label>
    @endif
    <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}"
        {{ $attributes->class([
            'block w-full rounded-xl border bg-card px-4 py-3 text-ink placeholder:text-muted/70 outline-none transition focus:border-brand focus:ring-4 focus:ring-brand/15',
            'border-rose-500' => $errors->has($model),
            'border-line' => ! $errors->has($model),
        ]) }}>
    @error($model)
        <p class="mt-1.5 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p>
    @elseif ($hint)
        <p class="mt-1.5 text-sm text-muted">{{ $hint }}</p>
    @enderror
</div>
