@props(['config', 'height' => 'h-56'])
{{--
    Gráfico Chart.js. $config = ['type' => 'bar|line|doughnut', 'data' => ['labels' => [...], 'datasets' => [...]], 'legend' => bool, 'stacked' => bool]
    Valores em CENTAVOS. Cores podem ser "var(--income)" etc. (seguem o tema).

    - wire:key muda quando os dados mudam → o Livewire troca o elemento e o gráfico é recriado.
    - wire:ignore impede o Livewire de "corrigir" o canvas quando os dados NÃO mudam: sem isso,
      ele apagava o tamanho/desenho feitos pelo Chart.js e o gráfico ficava em branco até
      redimensionar a janela.
--}}
<div wire:key="chart-{{ md5(json_encode($config)) }}" wire:ignore x-data="chart(@js($config))" {{ $attributes->class(['relative', $height]) }}>
    <canvas x-ref="canvas" role="img" aria-label="Gráfico"></canvas>
</div>
