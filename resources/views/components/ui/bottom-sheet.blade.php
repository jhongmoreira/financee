@props(['name', 'title' => null])
{{--
    Abrir:  $dispatch('open-sheet', 'nome')   |  Livewire: $this->dispatch('open-sheet', 'nome')
    Fechar: $dispatch('close-sheet', 'nome')  |  close() dentro do conteúdo
    No desktop vira um modal centralizado. Arraste para baixo para fechar no celular.
--}}
<div x-data="{
        open: false,
        dragY: 0,
        startY: null,
        close() { this.open = false; this.dragY = 0 },
        start(e) { this.startY = e.touches[0].clientY },
        move(e) { if (this.startY !== null) this.dragY = Math.max(0, e.touches[0].clientY - this.startY) },
        end() { if (this.dragY > 100) this.close(); this.dragY = 0; this.startY = null },
     }"
     x-on:open-sheet.window="if ($event.detail === @js($name) || $event.detail?.[0] === @js($name)) open = true"
     x-on:close-sheet.window="if ($event.detail === @js($name) || $event.detail?.[0] === @js($name)) close()"
     x-on:keydown.escape.window="close()"
     x-effect="document.body.style.overflow = open ? 'hidden' : ''"
     class="relative z-50" x-cloak>
    <div x-show="open" x-transition.opacity.duration.200ms @click="close()" class="fixed inset-0 bg-black/50"></div>

    <div x-show="open"
         x-transition:enter="transition duration-250 ease-out" x-transition:enter-start="translate-y-full lg:translate-y-4 lg:opacity-0" x-transition:enter-end="translate-y-0 lg:opacity-100"
         x-transition:leave="transition duration-200 ease-in" x-transition:leave-start="translate-y-0 lg:opacity-100" x-transition:leave-end="translate-y-full lg:translate-y-4 lg:opacity-0"
         :style="dragY ? `transform: translateY(${dragY}px)` : ''"
         role="dialog" aria-modal="true" @if ($title) aria-label="{{ $title }}" @endif
         class="fixed inset-x-0 bottom-0 max-h-[90dvh] overflow-y-auto rounded-t-3xl bg-card pb-safe shadow-2xl lg:inset-x-auto lg:bottom-auto lg:left-1/2 lg:top-1/2 lg:w-full lg:max-w-md lg:-translate-x-1/2 lg:-translate-y-1/2 lg:rounded-3xl">
        <div class="sticky top-0 z-10 bg-card px-5 pt-3" @touchstart="start" @touchmove="move" @touchend="end">
            <div class="mx-auto mb-3 h-1.5 w-10 rounded-full bg-line lg:hidden"></div>
            @if ($title)
                <div class="flex items-center justify-between pb-3">
                    <h2 class="text-lg font-semibold">{{ $title }}</h2>
                    <button type="button" @click="close()" class="tap -mr-2 rounded-full p-2 text-muted hover:bg-card-hover" aria-label="Fechar">
                        @svg('heroicon-o-x-mark', 'size-5')
                    </button>
                </div>
            @endif
        </div>
        <div class="px-5 pb-6">
            {{ $slot }}
        </div>
    </div>
</div>
