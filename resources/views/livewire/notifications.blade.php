<div>
    <x-ui.app-bar title="Notificações" :back="route('more')" />

    <div class="space-y-5 px-4 pt-2">
        {{-- Este dispositivo --}}
        <div x-data="pushDevice(@js($publicKey), @js(route('push.subscribe')), @js(route('push.unsubscribe')))">
            <x-ui.card class="p-4">
                <div class="flex items-start gap-3">
                    <x-ui.badge-avatar color="#ea580c" icon="bell" class="size-10" />
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold">Neste dispositivo</p>
                        <p class="text-sm text-muted" x-show="subscribed">Ativadas. Você recebe avisos mesmo com o app fechado.</p>
                        <p class="text-sm text-muted" x-show="! subscribed && supported && ! needsInstall">Receba avisos de contas a vencer, faturas e orçamentos.</p>
                        <p class="text-sm text-muted" x-show="! supported && ! needsInstall" x-cloak>Este navegador não suporta notificações push.</p>
                    </div>
                </div>

                <div x-show="needsInstall" x-cloak class="mt-3 rounded-xl bg-sky-500/10 p-3 text-sm text-sky-800 dark:text-sky-200">
                    No iPhone, as notificações só funcionam com o Financee <strong>instalado na tela de início</strong> (iOS 16.4+).
                    <button type="button" class="font-semibold underline" x-on:click="$store.install.prompt()">Ver como instalar</button>
                </div>

                <div class="mt-4 grid gap-2" x-show="supported && ! needsInstall" x-cloak>
                    <x-ui.button type="button" x-show="! subscribed" x-on:click="enable()" x-bind:disabled="busy">Ativar notificações</x-ui.button>
                    <template x-if="subscribed">
                        <div class="grid grid-cols-2 gap-2">
                            <x-ui.button type="button" variant="secondary" wire:click="sendTest">Enviar teste</x-ui.button>
                            <x-ui.button type="button" variant="secondary" x-on:click="disable()" x-bind:disabled="busy">Desativar</x-ui.button>
                        </div>
                    </template>
                    <p class="text-xs text-muted" x-show="permission === 'denied'">As notificações estão bloqueadas no navegador. Libere nas configurações do site.</p>
                </div>
            </x-ui.card>
        </div>

        {{-- Tipos de aviso --}}
        <x-ui.section title="O que avisar">
            <x-ui.card class="divide-y divide-line">
                @foreach (\App\Models\User::NOTIFICATION_TYPES as $type => [$label, $description])
                    <div class="px-4 py-3" wire:key="pref-{{ $type }}">
                        <x-ui.toggle :label="$label" :description="$description" wire:model.live="prefs.{{ $type }}" />
                        @if ($type === 'due' && ($prefs['due'] ?? true))
                            <div class="mt-3 flex items-center justify-between gap-3 text-sm">
                                <span class="text-muted">Avisar com antecedência de</span>
                                <select wire:model.live="dueDays" class="rounded-lg border border-line bg-card px-3 py-1.5" aria-label="Dias de antecedência">
                                    <option value="0">só no dia</option>
                                    @foreach ([1, 2, 3, 5, 7] as $d)
                                        <option value="{{ $d }}">{{ $d }} {{ $d === 1 ? 'dia' : 'dias' }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                    </div>
                @endforeach
            </x-ui.card>
            <p class="mt-2 px-1 text-xs text-muted">Os avisos do dia são enviados às 9h. Alertas de orçamento chegam logo após o lançamento.</p>
        </x-ui.section>

        {{-- Dispositivos --}}
        @if ($devices->isNotEmpty())
            <x-ui.section title="Dispositivos ativos">
                <x-ui.card class="divide-y divide-line">
                    @foreach ($devices as $device)
                        <div class="flex items-center gap-3 px-4 py-3" wire:key="dev-{{ $device['id'] }}">
                            @svg('heroicon-o-device-phone-mobile', 'size-6 text-muted')
                            <span class="min-w-0 flex-1">
                                <span class="block truncate font-medium">{{ $device['name'] }}</span>
                                <span class="block text-xs text-muted">desde {{ $device['since'] }}</span>
                            </span>
                            <button type="button" wire:click="removeDevice({{ $device['id'] }})" class="tap rounded-full p-2 text-muted hover:bg-card-hover" aria-label="Remover dispositivo">
                                @svg('heroicon-o-trash', 'size-5')
                            </button>
                        </div>
                    @endforeach
                </x-ui.card>
            </x-ui.section>
        @endif

        {{-- Histórico --}}
        <x-ui.section title="Avisos recentes">
            <x-ui.card class="divide-y divide-line overflow-hidden">
                @forelse ($items as $n)
                    <a href="{{ $n->data['url'] ?? '#' }}" wire:navigate wire:key="n-{{ $n->id }}" class="tap flex items-start gap-3 px-4 py-3 hover:bg-card-hover">
                        <x-ui.badge-avatar color="#ea580c" :icon="$n->data['icon'] ?? 'bell'" class="size-9" />
                        <span class="min-w-0 flex-1">
                            <span class="block font-medium">{{ $n->data['title'] ?? 'Aviso' }}</span>
                            <span class="block text-sm text-muted">{{ $n->data['body'] ?? '' }}</span>
                            <span class="block text-xs text-muted">{{ $n->created_at?->locale('pt_BR')->diffForHumans() }}</span>
                        </span>
                    </a>
                @empty
                    <x-ui.empty-state icon="bell" title="Nenhum aviso ainda" />
                @endforelse
            </x-ui.card>
            @if ($items->isNotEmpty())
                <button type="button" wire:click="clearAll" class="mt-2 w-full text-center text-sm text-muted">Limpar histórico</button>
            @endif
        </x-ui.section>
    </div>
</div>
