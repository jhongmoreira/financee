@props(['row', 'showPay' => true])
{{-- Linha de lançamento/transferência (extrato e Início). Toque abre a edição. --}}
@php
    $isTransfer = $row['kind'] === 'transfer';
    $neutral = $row['neutral'] ?? false;
@endphp
<div class="flex items-center gap-3 px-4 py-3 hover:bg-card-hover">
    @if (! empty($row['href']))
    <a href="{{ $row['href'] }}" wire:navigate class="tap flex min-w-0 flex-1 items-center gap-3 text-left">
    @else
    <button type="button" class="tap flex min-w-0 flex-1 items-center gap-3 text-left"
            x-on:click="$dispatch('edit-entry', { kind: '{{ $isTransfer ? 'transfer' : 'transaction' }}', id: {{ $row['id'] }} })">
    @endif
        <span class="relative">
            <x-ui.badge-avatar :color="$row['color']" :icon="$row['icon']" class="size-10" />
            @unless ($row['paid'])
                <span @class(['absolute -bottom-0.5 -right-0.5 size-3.5 rounded-full ring-2 ring-card', 'bg-rose-500' => $row['overdue'], 'bg-amber-400' => ! $row['overdue']])></span>
            @endunless
        </span>
        <span class="min-w-0 flex-1">
            <span class="block truncate font-medium">{{ $row['description'] }}</span>
            <span class="block truncate text-sm text-muted">
                @unless ($row['paid'] || ($row['hideStatus'] ?? false))
                    <span @class(['font-medium', 'text-rose-600 dark:text-rose-400' => $row['overdue'], 'text-amber-600 dark:text-amber-400' => ! $row['overdue']])>{{ $row['overdue'] ? 'Vencido' : 'Pendente' }}</span> ·
                @endunless
                {{ $row['subtitle'] }}
            </span>
        </span>
    @if (! empty($row['href'])) </a> @else </button> @endif
    <div class="flex shrink-0 flex-col items-end gap-1">
        <x-ui.money :value="$row['amount']" @class([
            'font-semibold',
            'text-income' => ! $neutral && $row['amount'] > 0,
            'text-expense' => ! $neutral && $row['amount'] < 0,
            'text-sky-600 dark:text-sky-400' => $neutral,
            'opacity-70' => ! $row['paid'],
        ]) />
        @if ($showPay && ! $row['paid'] && in_array($row['kind'], ['transaction', 'transfer'], true))
            <button type="button" wire:click="togglePaid('{{ $isTransfer ? 'transfer' : 'transaction' }}', {{ $row['id'] }})"
                    class="tap rounded-full bg-brand-soft px-2.5 py-0.5 text-xs font-semibold text-brand-strong">
                {{ $row['type'] === 'income' ? 'Receber' : ($isTransfer ? 'Efetivar' : 'Pagar') }}
            </button>
        @endif
    </div>
</div>
