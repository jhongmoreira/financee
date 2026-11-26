<div>
    <x-ui.app-bar title="Histórico" :back="route('admin.users')" />

    <div class="mx-auto max-w-3xl space-y-4 px-4 pt-2">
        <x-ui.card class="grid grid-cols-1 gap-3 p-4 sm:grid-cols-2">
            <x-ui.select label="Usuário" name="usuario" wire:model.live="usuario" placeholder="Todos" :options="$users->pluck('name', 'id')->all()" />
            <x-ui.select label="Tipo" name="tipo" wire:model.live="tipo" placeholder="Tudo" :options="collect(\App\Models\ActivityLog::GROUPS)->map(fn ($g) => $g[0])->all()" />
            <x-ui.input label="De" name="de" type="date" wire:model.live="de" />
            <x-ui.input label="Até" name="ate" type="date" wire:model.live="ate" />
        </x-ui.card>

        <p class="text-xs text-muted">O app instalado não pede senha ao reabrir: aparece como “Abriu o app” (depois de 30 min sem uso) ou “Entrou automaticamente”. Guardamos 180 dias.</p>

        <div wire:loading.class="opacity-60" class="space-y-4">
            @forelse ($logs as $day => $items)
                <section wire:key="d-{{ $day }}">
                    <h2 class="mb-2 px-1 text-xs font-semibold uppercase tracking-wider text-muted">{{ \Carbon\CarbonImmutable::parse($day)->locale('pt_BR')->translatedFormat('l, d \d\e F \d\e Y') }}</h2>
                    <x-ui.card class="divide-y divide-line overflow-hidden">
                        @foreach ($items as $log)
                            @php [$icon, $color] = $log->style(); @endphp
                            <div class="flex items-start gap-3 px-4 py-2.5" wire:key="l-{{ $log->id }}">
                                <span class="mt-0.5 shrink-0 {{ $color }}">@svg('heroicon-o-'.$icon, 'size-5')</span>
                                <div class="min-w-0 flex-1">
                                    <p class="break-words text-sm">
                                        <span class="font-semibold">{{ $log->user?->firstName() ?? $log->user_email ?? 'Desconhecido' }}</span>
                                        {{ $log->description }}
                                    </p>
                                    <p class="truncate text-xs text-muted">
                                        {{ $log->created_at->format('H:i:s') }}
                                        @if ($log->device()) · {{ $log->device() }} @endif
                                        @if ($log->ip) · {{ $log->ip }} @endif
                                        @if (! empty($log->meta['campos'])) · campos: {{ implode(', ', $log->meta['campos']) }} @endif
                                    </p>
                                </div>
                            </div>
                        @endforeach
                    </x-ui.card>
                </section>
            @empty
                <x-ui.card><x-ui.empty-state icon="clock" title="Nada registrado" description="Ajuste os filtros ou aguarde novas atividades." /></x-ui.card>
            @endforelse

            @if ($hasMore)
                <button type="button" wire:click="more" class="tap w-full rounded-xl border border-line bg-card py-3 text-sm font-semibold text-brand hover:bg-card-hover">Carregar mais</button>
            @endif
        </div>
    </div>
</div>
