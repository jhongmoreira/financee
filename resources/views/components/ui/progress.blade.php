@props(['pct' => 0])
{{-- Barra de progresso de orçamento: verde < 80%, amarelo 80–100%, vermelho > 100% --}}
<span {{ $attributes->class('block h-2 overflow-hidden rounded-full bg-canvas') }}>
    <span @class(['block h-full rounded-full transition-all', 'bg-brand' => $pct < 80, 'bg-amber-500' => $pct >= 80 && $pct <= 100, 'bg-rose-500' => $pct > 100])
          style="width: {{ min(100, max(0, $pct)) }}%"></span>
</span>
