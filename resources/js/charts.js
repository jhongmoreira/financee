/*
| Gráficos (Chart.js), carregados só quando uma tela tem gráfico.
| As cores vêm das variáveis CSS do tema e o gráfico é refeito ao trocar o tema.
*/
import {
    Chart, ArcElement, BarController, BarElement, CategoryScale, DoughnutController,
    Filler, LinearScale, LineController, LineElement, PointElement, Tooltip, Legend,
} from 'chart.js';

Chart.register(ArcElement, BarController, BarElement, CategoryScale, DoughnutController, Filler,
    LinearScale, LineController, LineElement, PointElement, Tooltip, Legend);

const css = (name) => getComputedStyle(document.documentElement).getPropertyValue(name).trim();

const brl = (cents) => (cents / 100).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
const brlShort = (cents) => {
    const v = cents / 100;
    if (Math.abs(v) >= 1000000) return 'R$ ' + (v / 1000000).toLocaleString('pt-BR', { maximumFractionDigits: 1 }) + ' mi';
    if (Math.abs(v) >= 1000) return 'R$ ' + (v / 1000).toLocaleString('pt-BR', { maximumFractionDigits: 1 }) + ' mil';
    return brl(cents);
};

/** Substitui tokens "var(--x)" pelas cores do tema atual. */
function resolveColors(value) {
    if (typeof value === 'string' && value.startsWith('var(')) return css(value.slice(4, -1));
    if (Array.isArray(value)) return value.map(resolveColors);
    return value;
}

export function create(canvas, config) {
    const hidden = () => window.Alpine?.store('privacy')?.hidden;
    const data = structuredClone(config.data);
    data.datasets.forEach((ds) => {
        ['backgroundColor', 'borderColor'].forEach((k) => { if (ds[k]) ds[k] = resolveColors(ds[k]); });
    });

    const muted = css('--muted');
    const line = css('--line');
    const isCircular = config.type === 'doughnut';

    return new Chart(canvas, {
        type: config.type,
        data,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: false, // sem animação: desenho instantâneo e sem quadros pendentes ao trocar de tela
            cutout: isCircular ? '68%' : undefined,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: config.legend ?? !isCircular, position: 'bottom', labels: { color: muted, boxWidth: 10, boxHeight: 10, usePointStyle: true } },
                tooltip: {
                    callbacks: {
                        label: (ctx) => `${ctx.dataset.label || ctx.label}: ${hidden() ? 'R$ ••••' : brl(ctx.raw)}`,
                    },
                },
            },
            scales: isCircular ? {} : {
                x: { grid: { display: false }, ticks: { color: muted, maxRotation: 0, autoSkipPadding: 6 }, border: { color: line }, stacked: config.stacked ?? false },
                y: {
                    grid: { color: line }, border: { display: false }, stacked: config.stacked ?? false,
                    ticks: { color: muted, maxTicksLimit: 5, callback: (v) => (hidden() ? '•••' : brlShort(v)) },
                },
            },
        },
    });
}
