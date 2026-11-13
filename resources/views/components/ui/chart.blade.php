@props(['config', 'height' => 'h-56'])
{{--
    Gráfico Chart.js. $config = ['type' => 'bar|line|doughnut', 'data' => ['labels' => [...], 'datasets' => [...]], 'legend' => bool, 'stacked' => bool]
    Valores em CENTAVOS. Cores podem ser "var(--income)" etc. (seguem o tema).
    O wire:key muda quando os dados mudam, recriando o gráfico após atualizações do Livewire.
--}}
<div wire:key="chart-{{ md5(json_encode($config)) }}" x-data="chart(@js($config))" {{ $attributes->class(['relative', $height]) }}>
    <canvas x-ref="canvas" role="img" aria-label="Gráfico"></canvas>
</div>
