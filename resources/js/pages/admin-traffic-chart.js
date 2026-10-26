/**
 * Admin Status — Traffic daily line chart (ALICE admin side).
 *
 * Converts the traffic panel's server-rendered CSS bars into a Chart.js line
 * chart, obtained through the shared lazy loader so the global bundle stays
 * lean. The canvas lives in a fixed-height container, so the chart can never
 * grow the layout. Server-rendered no-JS fallback stands otherwise.
 *
 * The tiles above the chart are tappable: a tap fetches that metric's series
 * from the JSON endpoint and redraws this same chart in place, mirroring the
 * dashboard explorer. A tap only repaints the chart — it never swaps the panel
 * or touches the URL, so it can't race the range chips in traffic-tabs.js. The
 * chips re-fetch the whole panel and reset the chart to the default metric,
 * which is the documented behaviour.
 *
 * `init()` is safe to call again after the traffic panel is swapped in place
 * by traffic-tabs.js — it destroys the previous instance and rebinds the tiles
 * on the freshly swapped DOM, so the old canvas never leaks a live Chart and
 * the old tile handlers never outlive their nodes.
 */

import { loadCharts } from '../charts.js';

let current = null;
let chart = null;
let inFlight = false;

function parseSeries(canvas) {
    if (!canvas) {
        return [];
    }

    try {
        const parsed = JSON.parse(canvas.dataset.series ?? '[]');
        return Array.isArray(parsed) ? parsed : [];
    } catch {
        return [];
    }
}

// Daily points carry "YYYY-MM-DD"; hourly points already carry a "G:00"
// label, so only split the ones that look like a date.
function pointLabel(point) {
    if (!point.date.includes('-')) {
        return point.date;
    }

    const [, m, d] = point.date.split('-');
    return `${m}/${d}`;
}

function lineData(points, unit) {
    return {
        labels: points.map(pointLabel),
        datasets: [
            {
                label: unit,
                data: points.map((point) => point.count ?? point.total ?? 0),
                borderColor: 'rgba(217, 119, 6, 0.8)',
                backgroundColor: 'rgba(217, 119, 6, 0.08)',
                fill: true,
                tension: 0.3,
                pointRadius: points.length > 45 ? 0 : 3,
            },
        ],
    };
}

function lineOptions(points, unit) {
    return {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: (ctx) => `${ctx.parsed.y} ${unit}`,
                },
            },
        },
        scales: {
            x: {
                grid: { display: false },
                ticks: points.length > 12
                    ? { maxRotation: 0, autoSkip: true, maxTicksLimit: 12 }
                    : {},
            },
            y: {
                beginAtZero: true,
                ticks: { precision: 0 },
                grid: { color: 'rgba(45, 35, 30, 0.08)' },
            },
        },
    };
}

async function fetchSeries(href) {
    try {
        const response = await fetch(href, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            return null;
        }

        return await response.json();
    } catch {
        return null;
    }
}

function markActive(tiles, metric) {
    tiles.forEach((tile) => {
        const active = tile.dataset.trafficMetric === metric;
        tile.toggleAttribute('data-traffic-active', active);
        tile.setAttribute('aria-current', active ? 'true' : 'false');
    });
}

export default function init() {
    if (current) {
        current.destroy();
        current = null;
        chart = null;
    }

    const canvas = document.querySelector('[data-traffic-chart-canvas]');
    const tiles = Array.from(document.querySelectorAll('[data-traffic-metric]'));
    const label = document.querySelector('[data-traffic-chart-label]');
    const wrap = document.querySelector('[data-traffic-chart-wrap]');

    if (!canvas) {
        return;
    }

    const endpoint = canvas.dataset.seriesEndpoint;
    const range = canvas.dataset.range;
    const initialUnit = canvas.dataset.unit ?? '';
    let activeMetric = canvas.dataset.metric ?? 'traffic.requests';

    // Handlers bind synchronously before any Chart.js work starts, so tiles
    // stay tappable even while the chart is still loading or has failed.
    const paint = (points, unit) => {
        if (!chart) {
            return;
        }

        chart.data = lineData(points, unit);
        chart.options = lineOptions(points, unit);
        chart.update();
    };

    tiles.forEach((tile) => {
        tile.addEventListener('click', (event) => {
            // The hint popover inside a tile handles its own toggle — never
            // let it trigger a series fetch too.
            event.preventDefault();

            if (event.target.closest('[data-info]')) {
                return;
            }

            const metric = tile.dataset.trafficMetric;
            if (!metric || metric === activeMetric || inFlight) {
                return;
            }

            activeMetric = metric;
            markActive(tiles, metric);
            inFlight = true;

            if (wrap) {
                wrap.style.opacity = '0.45';
            }

            const url = new URL(endpoint, window.location.origin);
            url.searchParams.set('metric', metric);
            url.searchParams.set('range', range ?? '24');

            fetchSeries(url.toString()).then((payload) => {
                inFlight = false;

                if (wrap) {
                    wrap.style.opacity = '';
                }

                if (!payload) {
                    return;
                }

                paint(payload.points ?? [], payload.unit ?? initialUnit);

                if (label) {
                    const chartLabel = label.textContent.replace(/^[^,]+, /, '');
                    label.textContent = `${payload.label} per day, ${chartLabel}`;
                }
            });
        });
    });

    const series = parseSeries(canvas);

    loadCharts()
        .then(({ Chart }) => {
            chart = new Chart(canvas, {
                type: 'line',
                data: lineData(series, initialUnit),
                options: lineOptions(series, initialUnit),
            });
        })
        .catch(() => {
            // Chart failed to load — the server-rendered fallback stands.
        });
}
