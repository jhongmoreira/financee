@props(['label' => 'Ícone'])
<div>
    <p class="mb-1.5 text-sm font-medium">{{ $label }}</p>
    <div class="grid max-h-48 grid-cols-7 gap-2 overflow-y-auto rounded-xl border border-line p-2" role="radiogroup" aria-label="{{ $label }}">
        @foreach (\App\Support\Palette::CATEGORY_ICONS as $icon)
            <label class="tap grid aspect-square cursor-pointer place-items-center rounded-xl text-muted has-[:checked]:bg-brand has-[:checked]:text-white">
                <input type="radio" value="{{ $icon }}" {{ $attributes->whereStartsWith('wire:model') }} class="sr-only" aria-label="{{ $icon }}">
                @svg('heroicon-o-'.$icon, 'size-6')
            </label>
        @endforeach
    </div>
</div>
