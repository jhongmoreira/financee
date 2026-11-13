@props(['value' => 0, 'signed' => false])
{{-- Valor em centavos. Respeita o "ocultar valores" ($store.privacy). --}}
<span x-data {{ $attributes->class('tabular-nums') }}>
    <span x-show="! $store.privacy.hidden">{{ \App\Support\Money::format((int) $value, signed: $signed) }}</span>
    <span x-show="$store.privacy.hidden" x-cloak>R$ ••••</span>
</span>
