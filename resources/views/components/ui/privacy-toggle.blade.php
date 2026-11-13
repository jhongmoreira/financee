<button type="button" x-data @click="$store.privacy.toggle()" {{ $attributes->class('tap rounded-full p-2 text-muted hover:bg-card') }}
        :aria-label="$store.privacy.hidden ? 'Mostrar valores' : 'Ocultar valores'">
    <span x-show="! $store.privacy.hidden">@svg('heroicon-o-eye', 'size-6')</span>
    <span x-show="$store.privacy.hidden" x-cloak>@svg('heroicon-o-eye-slash', 'size-6')</span>
</button>
