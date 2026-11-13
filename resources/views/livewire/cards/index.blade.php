<div>
    <x-ui.app-bar title="Cartões de crédito" :back="route('more')">
        <x-slot:actions>
            <x-ui.privacy-toggle />
            <button type="button" wire:click="create" class="tap rounded-full p-2 text-brand hover:bg-card" aria-label="Novo cartão">
                @svg('heroicon-o-plus-circle', 'size-7')
            </button>
        </x-slot:actions>
    </x-ui.app-bar>

    <div class="space-y-4 px-4 pt-2">
        @forelse ($this->cards as $card)
            <button type="button" wire:key="card-{{ $card->id }}" wire:click="edit({{ $card->id }})"
                    @class(['tap relative block aspect-[1.75] w-full overflow-hidden rounded-3xl p-5 text-left text-white shadow-lg', 'opacity-60 grayscale' => $card->archived_at])
                    style="background: linear-gradient(135deg, {{ $card->color }}, color-mix(in srgb, {{ $card->color }} 55%, black))">
                <span class="pointer-events-none absolute -right-10 -top-10 size-40 rounded-full bg-white/10"></span>
                <span class="pointer-events-none absolute -bottom-16 -left-6 size-44 rounded-full bg-white/5"></span>

                <span class="relative flex h-full flex-col justify-between">
                    <span class="flex items-start justify-between gap-2">
                        <span class="min-w-0">
                            <span class="block truncate text-lg font-semibold">{{ $card->name }}</span>
                            <span class="block text-xs text-white/75">{{ $card->institution?->name ?? 'Cartão de crédito' }}{{ $card->archived_at ? ' · Arquivado' : '' }}</span>
                        </span>
                        <span class="shrink-0 rounded-md bg-white/20 px-2 py-0.5 text-xs font-semibold uppercase tracking-wide">{{ $card->brandLabel() }}</span>
                    </span>

                    <span class="grid grid-cols-[1fr_auto_auto] gap-5 text-xs">
                        <span>
                            <span class="block text-white/70">Limite</span>
                            <x-ui.money :value="$card->limit" class="block whitespace-nowrap text-sm font-semibold" />
                        </span>
                        <span>
                            <span class="block text-white/70">Fecha dia</span>
                            <span class="block text-sm font-semibold">{{ $card->closing_day }}</span>
                        </span>
                        <span>
                            <span class="block text-white/70">Vence dia</span>
                            <span class="block text-sm font-semibold">{{ $card->due_day }}</span>
                        </span>
                    </span>
                </span>
            </button>
        @empty
            <x-ui.card>
                <x-ui.empty-state icon="credit-card" title="Nenhum cartão cadastrado" description="Cadastre seus cartões para acompanhar faturas, limites e parcelas.">
                    <x-ui.button type="button" wire:click="create">@svg('heroicon-o-plus', 'size-5') Novo cartão</x-ui.button>
                </x-ui.empty-state>
            </x-ui.card>
        @endforelse

        <label class="flex cursor-pointer items-center justify-center gap-2 text-sm text-muted">
            <input type="checkbox" wire:model.live="showArchived" class="size-4 rounded accent-emerald-600"> Mostrar arquivados
        </label>
    </div>

    <x-ui.bottom-sheet name="card-form" :title="$editingId ? 'Editar cartão' : 'Novo cartão'">
        <form wire:submit="save" class="space-y-4">
            <x-ui.select label="Banco emissor" name="institution_id" wire:model.live="institution_id" :options="$institutions" placeholder="Selecione" />
            <x-ui.input label="Nome do cartão" name="name" wire:model="name" placeholder="Ex.: Nubank Ultravioleta" maxlength="60" />
            <div class="grid grid-cols-2 gap-3">
                <x-ui.select label="Bandeira" name="brand" wire:model="brand" :options="\App\Models\CreditCard::BRANDS" />
                <x-ui.money-input label="Limite" name="limit" wire:model="limit" />
            </div>
            <div class="grid grid-cols-2 gap-3">
                <x-ui.select label="Dia de fechamento" name="closing_day" wire:model="closing_day" :options="$days" />
                <x-ui.select label="Dia de vencimento" name="due_day" wire:model="due_day" :options="$days" />
            </div>
            <x-ui.select label="Conta para pagar a fatura" name="payment_account_id" wire:model="payment_account_id" :options="$accounts" placeholder="Escolher na hora" />
            <x-ui.color-picker wire:model="color" />

            <x-ui.button class="w-full">{{ $editingId ? 'Salvar alterações' : 'Criar cartão' }}</x-ui.button>

            @if ($editing)
                <div class="grid grid-cols-2 gap-3">
                    <x-ui.button type="button" variant="secondary" wire:click="toggleArchive">
                        {{ $editing->archived_at ? 'Reativar' : 'Arquivar' }}
                    </x-ui.button>
                    <x-ui.button type="button" variant="secondary" class="!text-rose-600 dark:!text-rose-400"
                        wire:click="delete" wire:confirm="Excluir o cartão “{{ $editing->name }}”? Esta ação não pode ser desfeita.">
                        Excluir
                    </x-ui.button>
                </div>
            @endif
        </form>
    </x-ui.bottom-sheet>
</div>
