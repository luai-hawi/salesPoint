import {
    ArcElement,
    BarController,
    BarElement,
    CategoryScale,
    Chart,
    DoughnutController,
    Filler,
    Legend,
    LinearScale,
    LineController,
    LineElement,
    PointElement,
    Tooltip,
} from 'chart.js';

Chart.register(
    ArcElement, BarController, BarElement, CategoryScale, DoughnutController, Filler,
    Legend, LinearScale, LineController, LineElement, PointElement, Tooltip,
);

export const PALETTE = ['#4f46e5', '#10b981', '#f59e0b', '#ef4444', '#0ea5e9', '#8b5cf6', '#ec4899', '#14b8a6', '#64748b', '#84cc16'];

const numberFormat = new Intl.NumberFormat('en-US', { maximumFractionDigits: 2 });

function withAlpha(hex, alpha) {
    const value = hex.replace('#', '');
    const r = parseInt(value.substring(0, 2), 16);
    const g = parseInt(value.substring(2, 4), 16);
    const b = parseInt(value.substring(4, 6), 16);

    return `rgba(${r}, ${g}, ${b}, ${alpha})`;
}

export function formatValue(value, spec = {}) {
    const number = Number(value) || 0;
    const formatted = numberFormat.format(number);
    if (spec.percent) return `${formatted}%`;
    if (spec.currency) return `${spec.currency}${formatted}`;

    return formatted;
}

export function buildConfig(spec, isRtl = false) {
    const type = spec.type === 'doughnut' ? 'doughnut' : (spec.type === 'line' ? 'line' : 'bar');
    const horizontal = type === 'bar' && !!spec.horizontal;
    const datasets = (spec.datasets || []).map((dataset, index) => {
        const color = dataset.color || PALETTE[index % PALETTE.length];
        const datasetType = dataset.type || type;
        const base = { label: dataset.label, data: (dataset.data || []).map((v) => Number(v) || 0) };

        if (type === 'doughnut') {
            const colors = (dataset.data || []).map((_, i) => (dataset.colors && dataset.colors[i]) || PALETTE[i % PALETTE.length]);
            return { ...base, backgroundColor: colors, borderColor: '#ffffff', borderWidth: 2, hoverOffset: 6 };
        }
        if (datasetType === 'line') {
            return {
                ...base,
                type: 'line',
                borderColor: color,
                backgroundColor: withAlpha(color, 0.12),
                fill: dataset.fill ?? (spec.datasets.length === 1),
                tension: 0.3,
                borderWidth: 2.5,
                pointRadius: base.data.length > 45 ? 0 : 3,
                pointHoverRadius: 5,
                order: 0,
            };
        }

        return {
            ...base,
            type: 'bar',
            backgroundColor: dataset.colors || withAlpha(color, 0.85),
            hoverBackgroundColor: color,
            borderRadius: 6,
            maxBarThickness: 38,
            order: 1,
        };
    });

    const valueSpec = { currency: spec.currency || '', percent: !!spec.percent };
    const tooltip = {
        rtl: isRtl,
        callbacks: {
            label(context) {
                const raw = type === 'doughnut' ? context.parsed : (horizontal ? context.parsed.x : context.parsed.y);
                const label = type === 'doughnut' ? context.label : context.dataset.label;
                let text = `${label}: ${formatValue(raw, valueSpec)}`;
                if (type === 'doughnut') {
                    const total = context.dataset.data.reduce((sum, v) => sum + Math.abs(Number(v) || 0), 0);
                    if (total > 0) text += ` (${numberFormat.format((Math.abs(raw) / total) * 100)}%)`;
                }
                return text;
            },
        },
    };
    const legend = {
        display: spec.legend ?? (type === 'doughnut' || datasets.length > 1),
        position: type === 'doughnut' ? (spec.legendPosition || 'bottom') : 'top',
        rtl: isRtl,
        labels: { usePointStyle: true, boxWidth: 8, padding: 14 },
    };

    const options = {
        responsive: true,
        maintainAspectRatio: false,
        animation: { duration: 500 },
        interaction: { mode: type === 'doughnut' ? 'nearest' : 'index', intersect: type === 'doughnut' },
        plugins: { legend, tooltip },
    };

    if (type === 'doughnut') {
        options.cutout = '64%';
    } else {
        const valueAxis = {
            beginAtZero: true,
            stacked: !!spec.stacked,
            grid: { color: 'rgba(148, 163, 184, 0.18)' },
            ticks: { callback: (v) => formatValue(v, { percent: valueSpec.percent }) },
        };
        const categoryAxis = {
            stacked: !!spec.stacked,
            grid: { display: false },
            ticks: { autoSkip: true, maxRotation: 0, maxTicksLimit: horizontal ? undefined : 12 },
        };
        if (horizontal) {
            options.indexAxis = 'y';
            options.scales = {
                x: { ...valueAxis, reverse: isRtl },
                y: { ...categoryAxis, position: isRtl ? 'right' : 'left' },
            };
        } else {
            options.scales = {
                x: { ...categoryAxis, reverse: isRtl },
                y: { ...valueAxis, position: isRtl ? 'right' : 'left' },
            };
        }
    }

    return { type, data: { labels: spec.labels || [], datasets }, options };
}

export function initCharts(root = document) {
    const isRtl = (document.documentElement.getAttribute('dir') || '').toLowerCase() === 'rtl';
    const font = window.getComputedStyle(document.body).fontFamily;
    if (font) Chart.defaults.font.family = font;
    Chart.defaults.color = '#4b5563';

    root.querySelectorAll('canvas[data-chart]').forEach((canvas) => {
        if (canvas.dataset.chartReady === '1') return;
        try {
            const spec = JSON.parse(canvas.dataset.chart);
            canvas.dataset.chartReady = '1';
            new Chart(canvas, buildConfig(spec, isRtl));
        } catch (error) {
            canvas.dataset.chartReady = 'error';
            console.error('Chart render failed', error);
        }
    });
}

window.HawiCharts = { init: initCharts };
