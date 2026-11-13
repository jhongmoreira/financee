@props(['label' => null, 'name', 'options' => [], 'placeholder' => null, 'hint' => null])
@php
    $model = $attributes->wire('model')->value() ?: $name;
@endphp
<div>
    @if ($label)
        <label for="f-{{ $model }}" class="mb-1.5 block text-sm font-medium">{{ $label }}</label>
    @endif
    <div class="relative">
        <select id="f-{{ $model }}" name="{{ $name }}"
            {{ $attributes->class([
                'block w-full appearance-none rounded-xl border bg-card py-3 pl-4 pr-10 text-ink outline-none transition focus:border-brand focus:ring-4 focus:ring-brand/15',
                'border-rose-500' => $errors->has($model),
                'border-line' => ! $errors->has($model),
            ]) }}>
            @if ($placeholder !== null)
                <option value="">{{ $placeholder }}</option>
            @endif
            @foreach ($options as $value => $text)
                <option value="{{ $value }}">{{ $text }}</option>
            @endforeach
        </select>
        @svg('heroicon-m-chevron-up-down', 'pointer-events-none absolute right-3 top-1/2 size-5 -translate-y-1/2 text-muted')
    </div>
    @error($model)
        <p class="mt-1.5 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p>
    @elseif ($hint)
        <p class="mt-1.5 text-sm text-muted">{{ $hint }}</p>
    @enderror
</div>
