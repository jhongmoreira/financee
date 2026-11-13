@props(['label' => 'Cor'])
<div>
    <p class="mb-1.5 text-sm font-medium">{{ $label }}</p>
    <div class="grid grid-cols-9 gap-2" role="radiogroup" aria-label="{{ $label }}">
        @foreach (\App\Support\Palette::COLORS as $color)
            <label class="tap relative grid aspect-square cursor-pointer place-items-center rounded-full ring-offset-2 ring-offset-card has-[:checked]:ring-2 has-[:checked]:ring-ink"
                   style="background-color: {{ $color }}">
                <input type="radio" value="{{ $color }}" {{ $attributes->whereStartsWith('wire:model') }} class="peer sr-only" aria-label="{{ $color }}">
                @svg('heroicon-m-check', 'hidden size-4 text-white peer-checked:block')
            </label>
        @endforeach
    </div>
</div>
