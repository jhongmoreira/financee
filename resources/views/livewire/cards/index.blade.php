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
            @php
                $invoice = $current[$card->id];
                $sum = $summaries[$invoice->id];
                $used = $card->usedLimit();
                $pct = $card->limit > 0 ? min(100, round($used / $card->limit * 100)) : 0;
            @endphp
            <div wire:key="card-{{ $card->id }}" @class(['relative', 'opacity-60 grayscale' => $card->archived_at])>
                <a href="{{ route('cards.invoice', $card) }}" wire:navigate
                   class="tap relative block overflow-hidden rounded-3xl p-5 text-white shadow-lg"
                   style="background: linear-gradient(135deg, {{ $card->color }}, color-mix(in srgb, {{ $card->color }} 55%, black))">
                    <span class="pointer-events-none absolute -right-10 -top-10 size-40 rounded-full bg-white/10"></span>

                    <span class="relative flex items-start justify-between gap-2 pr-10">
                        <span class="min-w-0">
                            <span class="block truncate text-lg font-semibold">{{ $card->name }}</span>
                            <span class="block text-xs text-white/75">{{ $card->brandLabel() }} · fecha dia {{ $card->closing_day }} · vence dia {{ $card->due_day }}{{ $card->archived_at ? ' · Arquivado' : '' }}</span>
                        </span>
                    </span>

                    <span class="relative mt-5 flex items-end justify-between gap-3">
                        <span>
                            <span class="block text-xs text-white/70">Fatura {{ $invoice->shortLabel() }} · {{ mb_strtolower($sum['label']) }}</span>
                            <x-ui.money :value="$sum['total']" class="block text-2xl font-bold" />
                        </span>
                        <span class="text-right text-xs text-white/80">vence<br><strong class="text-sm text-white">{{ $invoice->due_date->format('d/m') }}</strong></span>
                    </span>

                    <span class="relative mt-4 block h-1.5 overflow-hidden rounded-full bg-white/20">
                        <span class="block h-full rounded-full bg-white" style="width: {{ $pct }}%"></span>
                    </span>
                    <span class="relative mt-1.5 flex justify-between text-xs text-white/80">
                        <span>Usado <x-ui.money :value="$used" /></span>
                        <span>Disponível <x-ui.money :value="$card->limit - $used" class="font-semibold text-white" /></span>
                    </span>
                </a>
                <button type="button" wire:click="edit({{ $card->id }})" class="tap absolute right-3 top-3 rounded-full bg-white/20 p-2 text-white" aria-label="Editar {{ $card->name }}">
                    @svg('heroicon-o-pencil-square', 'size-5')
                </button>
            </div>
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
