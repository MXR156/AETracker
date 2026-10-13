// Small Chart.js helpers shared by the pages.
window.AE = {
    palette: ['#1f8a5b', '#1b6fb8', '#c2410c', '#7c3aed', '#be185d', '#0e7490', '#a16207', '#4d7c0f', '#b91c1c', '#475569', '#9333ea', '#0f766e'],

    textColor() {
        return getComputedStyle(document.documentElement).getPropertyValue('--muted').trim() || '#6b7785';
    },

    gridColor() {
        return getComputedStyle(document.documentElement).getPropertyValue('--border').trim() || '#e2e7ec';
    },

    baseOptions(extra = {}) {
        const text = this.textColor(), grid = this.gridColor();
        return Object.assign({
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: { legend: { labels: { color: text, boxWidth: 12 } } },
            scales: {
                x: { ticks: { color: text }, grid: { color: grid } },
                y: { ticks: { color: text }, grid: { color: grid }, beginAtZero: true },
            },
        }, extra);
    },

    // Lines per player; "me" is drawn thicker and dashed so it stands out.
    playerLines(el, data) {
        const datasets = data.datasets.map((d, i) => {
            const color = this.palette[i % this.palette.length];
            return {
                label: d.label + (d.isMe ? ' (me)' : ''),
                data: d.data,
                borderColor: color,
                backgroundColor: color,
                borderWidth: d.isMe ? 4 : 2,
                borderDash: d.isMe ? [6, 4] : [],
                tension: 0.2,
                spanGaps: true,
                pointRadius: 3,
            };
        });
        return new Chart(el, { type: 'line', data: { labels: data.labels, datasets }, options: this.baseOptions() });
    },
};
