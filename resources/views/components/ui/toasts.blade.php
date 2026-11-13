<div x-data
     @if (session('toast')) x-init="$nextTick(() => $store.toast.show(@js(session('toast'))))" @endif
     class="pointer-events-none fixed inset-x-0 top-0 z-[60] flex flex-col items-center gap-2 px-4 pt-safe">
    <template x-for="toast in $store.toast.items" :key="toast.id">
        <div x-transition.opacity @click="$store.toast.dismiss(toast.id)"
             class="pointer-events-auto mt-3 flex w-full max-w-sm items-center gap-3 rounded-2xl bg-gray-900 px-4 py-3 text-sm text-white shadow-xl dark:bg-gray-100 dark:text-gray-900">
            <span class="grid size-6 shrink-0 place-items-center rounded-full"
                  :class="{ 'bg-emerald-500': toast.type === 'success', 'bg-rose-500': toast.type === 'error', 'bg-sky-500': toast.type === 'info' }">
                <span x-show="toast.type === 'success'">@svg('heroicon-m-check', 'size-4 text-white')</span>
                <span x-show="toast.type === 'error'">@svg('heroicon-m-exclamation-triangle', 'size-4 text-white')</span>
                <span x-show="toast.type === 'info'">@svg('heroicon-m-information-circle', 'size-4 text-white')</span>
            </span>
            <span x-text="toast.message"></span>
        </div>
    </template>
</div>
