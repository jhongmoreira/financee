@props(['label'])
<div class="flex items-center justify-between px-2 pb-2">
    <button type="button" wire:click="previousMonth" class="tap rounded-full p-2 text-muted hover:bg-card" aria-label="Mês anterior">
        @svg('heroicon-o-chevron-left', 'size-5')
    </button>
    <span class="text-base font-semibold" wire:loading.class="opacity-50" wire:target="previousMonth,nextMonth">{{ $label }}</span>
    <button type="button" wire:click="nextMonth" class="tap rounded-full p-2 text-muted hover:bg-card" aria-label="Próximo mês">
        @svg('heroicon-o-chevron-right', 'size-5')
    </button>
</div>
