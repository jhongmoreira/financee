@props(['label', 'description' => null])
<label class="flex cursor-pointer items-center justify-between gap-4">
    <span>
        <span class="block text-sm font-medium">{{ $label }}</span>
        @if ($description)
            <span class="block text-sm text-muted">{{ $description }}</span>
        @endif
    </span>
    <span class="relative inline-flex shrink-0">
        <input type="checkbox" {{ $attributes->whereStartsWith('wire:model') }} class="peer sr-only">
        <span class="h-7 w-12 rounded-full bg-line transition peer-checked:bg-brand"></span>
        <span class="absolute left-0.5 top-0.5 size-6 rounded-full bg-white shadow transition peer-checked:translate-x-5"></span>
    </span>
</label>
