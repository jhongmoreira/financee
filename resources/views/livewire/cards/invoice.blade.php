@php
    $statusColors = [
        'future' => 'bg-slate-500/15 text-slate-600 dark:text-slate-300',
        'open' => 'bg-sky-500/15 text-sky-700 dark:text-sky-300',
        'closed' => 'bg-amber-500/15 text-amber-700 dark:text-amber-300',
        'partial' => 'bg-amber-500/15 text-amber-700 dark:text-amber-300',
        'paid' => 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300',
        'overdue' => 'bg-rose-500/15 text-rose-700 dark:text-rose-300',
    ];
    $limitPct = $card->limit > 0 ? min(100, round($used / $card->limit * 100)) : 0;
@endphp
<div x-data="{ startX: null }"
     x-on:touchstart.passive="startX = $event.touches[0].clientX"
     x-on:touchend="if (startX !== null) { const dx = $event.changedTouches[0].clientX - startX; if (Math.abs(dx) > 80 && !document.body.style.overflow) { dx > 0 ? $wire.previousMonth() : $wire.nextMonth() } startX = null }">
    <x-ui.app-bar :title="$card->name" :back="route('cards')">
        <x-slot:actions><x-ui.privacy-toggle /></x-slot:actions>
        <x-slot:below>
            <x-ui.month-switcher :label="'Fatura de '.mb_strtolower(\App\Support\Month::label($invoice->month()))" />
        </x-slot:below>
    </x-ui.app-bar>

    <div class="space-y-4 px-4 pt-1" wire:loading.class="opacity-60" wire:target="previousMonth,nextMonth">
        {{-- Resumo da fatura --}}
        <div class="overflow-hidden rounded-3xl p-5 text-white shadow-lg"
             style="background: linear-gradient(135deg, {{ $card->color }}, color-mix(in srgb, {{ $card->color }} 55%, black))">
            <div class="flex items-start justify-between gap-2">
                <div>
                    <p class="text-sm text-white/75">{{ in_array($summary['status'], ['paid']) ? 'Fatura paga' : 'Total da fatura' }}</p>
                    <x-ui.money :value="$summary['total']" class="block text-3xl font-bold" />
                </div>
                <span class="rounded-full bg-white/20 px-3 py-1 text-xs font-semibold">{{ $summary['label'] }}</span>
            </div>
            <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                <div><span class="block text-xs text-white/70">Fecha em</span><span class="font-semibold">{{ $invoice->closing_date->format('d/m/Y') }}</span></div>
                <div><span class="block text-xs text-white/70">Vence em</span><span class="font-semibold">{{ $invoice->due_date->format('d/m/Y') }}</span></div>
            </div>
            @if (! in_array($summary['status'], ['paid', 'future']) && $summary['remaining'] > 0)
                <button type="button" wire:click="startPayment" class="tap mt-5 w-full rounded-xl bg-white py-3 font-semibold text-ink">
                    Pagar fatura · <x-ui.money :value="$summary['remaining']" />
                </button>
            @endif
        </div>

        {{-- Detalhamento --}}
        <x-ui.card class="divide-y divide-line text-sm">
            <div class="flex justify-between px-4 py-2.5"><span class="text-muted">Compras</span><x-ui.money :value="$summary['purchases']" /></div>
            @if ($summary['refunds'])
                <div class="flex justify-between px-4 py-2.5"><span class="text-muted">Estornos e créditos</span><x-ui.money :value="-$summary['refunds']" class="text-income" /></div>
            @endif
            @if ($summary['carried_in'])
                <div class="flex justify-between px-4 py-2.5"><span class="text-muted">{{ $summary['carried_in'] > 0 ? 'Saldo da fatura anterior' : 'Crédito da fatura anterior' }}</span><x-ui.money :value="$summary['carried_in']" @class(['text-income' => $summary['carried_in'] < 0]) /></div>
            @endif
            @if ($summary['paid'])
                <div class="flex justify-between px-4 py-2.5"><span class="text-muted">Pagamentos</span><x-ui.money :value="-$summary['paid']" class="text-income" /></div>
            @endif
            @if ($summary['carried_out'])
                <div class="flex justify-between px-4 py-2.5"><span class="text-muted">{{ $summary['carried_out'] > 0 ? 'Restante levado para a próxima' : 'Crédito levado para a próxima' }}</span><x-ui.money :value="-$summary['carried_out']" /></div>
            @endif
            <div class="flex justify-between px-4 py-3 font-semibold">
                <span>{{ $summary['remaining'] >= 0 ? 'Falta pagar' : 'Crédito' }}</span>
                <x-ui.money :value="abs($summary['remaining'])" @class(['text-expense' => $summary['remaining'] > 0 && $summary['status'] === 'overdue']) />
            </div>
        </x-ui.card>

        {{-- Limite --}}
        <x-ui.card class="p-4">
            <div class="mb-2 flex items-baseline justify-between gap-3 text-sm">
                <span class="text-muted">Limite usado</span>
                <x-ui.money :value="$used" class="font-semibold" />
            </div>
            <div class="h-2 overflow-hidden rounded-full bg-canvas">
                <div class="h-full rounded-full {{ $limitPct >= 90 ? 'bg-rose-500' : ($limitPct >= 70 ? 'bg-amber-500' : 'bg-brand') }}" style="width: {{ $limitPct }}%"></div>
            </div>
            <div class="mt-2 flex justify-between gap-3 text-sm text-muted">
                <span>Disponível <x-ui.money :value="$card->limit - $used" class="font-semibold text-ink" /></span>
                <span>Limite <x-ui.money :value="$card->limit" /></span>
            </div>
        </x-ui.card>

        {{-- Pagamentos --}}
        @if ($payments->isNotEmpty())
            <section>
                <h2 class="mb-1.5 px-1 text-xs font-semibold uppercase tracking-wider text-muted">Pagamentos</h2>
                <x-ui.card class="divide-y divide-line">
                    @foreach ($payments as $payment)
                        <div wire:key="pay-{{ $payment->id }}" class="flex items-center gap-3 px-4 py-3">
                            <x-ui.badge-avatar color="#059669" icon="banknotes" class="size-10" />
                            <span class="min-w-0 flex-1">
                                <span class="block font-medium">{{ $payment->paid_at->format('d/m/Y') }}</span>
                                <span class="block truncate text-sm text-muted">{{ $payment->account?->name }}</span>
                            </span>
                            <x-ui.money :value="$payment->amount" class="font-semibold" />
                            <button type="button" wire:click="undoPayment({{ $payment->id }})" wire:confirm="Desfazer este pagamento?"
                                    class="tap rounded-full p-2 text-muted hover:bg-card-hover" aria-label="Desfazer pagamento">
                                @svg('heroicon-o-arrow-uturn-left', 'size-5')
                            </button>
                        </div>
                    @endforeach
                </x-ui.card>
            </section>
        @endif

        {{-- Lançamentos da fatura --}}
        @forelse ($groups as $day => $rows)
            <section wire:key="iday-{{ $day }}">
                <h2 class="mb-1.5 px-1 text-xs font-semibold uppercase tracking-wider text-muted">{{ \App\Support\Month::dayLabel(\Carbon\CarbonImmutable::parse($day)) }}</h2>
                <x-ui.card class="divide-y divide-line overflow-hidden">
                    @foreach ($rows as $row)
                        <div wire:key="it-{{ $row['id'] }}"><x-ui.entry-row :row="$row" :show-pay="false" /></div>
                    @endforeach
                </x-ui.card>
            </section>
        @empty
            <x-ui.card>
                <x-ui.empty-state icon="credit-card" title="Nenhuma compra nesta fatura">
                    <x-ui.button type="button" x-on:click="$dispatch('open-sheet', 'entry-form'); $dispatch('open-entry', { kind: 'card', card: '{{ $card->id }}' })">
                        @svg('heroicon-o-plus', 'size-5') Lançar compra
                    </x-ui.button>
                </x-ui.empty-state>
            </x-ui.card>
        @endforelse
    </div>

    {{-- Pagar fatura --}}
    <x-ui.bottom-sheet name="pay-invoice" title="Pagar fatura">
        <form wire:submit="pay" class="space-y-4">
            <x-ui.money-input label="Valor do pagamento" name="pay_amount" wire:model="pay_amount"
                :hint="'Falta pagar: '.\App\Support\Money::format(max(0, $summary['remaining'])).'. Se pagar a mais, o excedente vira crédito na próxima fatura.'" />
            <x-ui.select label="Pagar com a conta" name="pay_account_id" wire:model="pay_account_id" :options="$accounts" placeholder="Selecione" />
            <x-ui.input label="Data do pagamento" name="pay_date" type="date" wire:model="pay_date" />
            <x-ui.toggle wire:model="carry_remainder" label="Pagamento parcial: levar o restante para a próxima fatura"
                description="Desligue para pagar o resto depois nesta mesma fatura." />
            <x-ui.button class="w-full">Confirmar pagamento</x-ui.button>
        </form>
    </x-ui.bottom-sheet>
</div>
