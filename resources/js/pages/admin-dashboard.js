/**
 * Admin dashboard charts.
 *
 * Two jobs:
 *   - the fixed Account growth and Onboarding bars;
 *   - the platform-health explorer: eight metric tiles share ONE chart, and
 *     tapping a tile fetches that metric from /admin/dashboard/series and
 *     redraws it in place. Tiles are real links, so with JS off the page
 *     simply reloads with the metric already selected server-side.
 *
 * Chart.js always comes through the shared lazy loader (resources/js/charts.js)
 * — never a static import, see FRONTEND_GUIDELINES.md.
 */

import { loadCharts } from '../charts.js';

const GRID = 'rgba(45, 35, 30, 0.08)';

function parseSeries(canvas) {
    if (!canvas) {
        return null;
    }

    try {
        const parsed = JSON.parse(canvas.dataset.series ?? '[]');
        return Array.isArray(parsed) ? parsed : null;
    } catch {
        return null;
    }
}

function shortDate(date) {
    const [, month, day] = date.split('-');
    return `${month}/${day}`;
}

function tickValues(points) {
    return points.map((point) => shortDate(point.date));
}

function formatValue(value, precision) {
    return precision > 0 ? Number(value).toFixed(precision) : Math.round(value).toLocaleString();
}

function lineOptions(points, metric) {
    return {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: (ctx) => `${formatValue(ctx.parsed.y, metric.precision)} ${metric.unit}`,
                },
            },
        },
        scales: {
            x: {
                grid: { display: false },
                ticks: points.length > 10
                    ? { maxRotation: 0, autoSkip: true, maxTicksLimit: 10 }
                    : {},
            },
            y: {
                beginAtZero: metric.zero,
                precision: metric.precision,
                grid: { color: GRID },
            },
        },
    };
}

function lineData(points, metric) {
    return {
        labels: tickValues(points),
        datasets: [
            {
                label: metric.label,
                data: points.map((point) => point.count),
                borderColor: 'rgba(217, 119, 6, 0.9)',
                backgroundColor: 'rgba(217, 119, 6, 0.14)',
                fill: true,
                tension: 0.3,
                pointRadius: points.length > 45 ? 0 : 2.5,
            },
        ],
    };
}

function readMetric(canvas) {
    return {
        label: canvas.dataset.label ?? '',
        unit: canvas.dataset.unit ?? '',
        precision: Number(canvas.dataset.precision ?? 0),
        zero: canvas.dataset.zero !== '0',
    };
}

function formatUptime(totalSeconds) {
    const seconds = Math.max(0, Math.floor(totalSeconds));
    const days = Math.floor(seconds / 86400);
    const hours = Math.floor((seconds % 86400) / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);

    // Seconds always print so the chip visibly ticks and reads as live at any
    // uptime. Matches the server-rendered first paint in dashboard.blade.
    return `${days}d ${hours}h ${minutes}m ${seconds % 60}s`;
}

// Live uptime chip. The boot timestamp is fixed, so this is pure local math
// on a 1s interval - no polling, no requests. An offset captured once at
// load absorbs any wrong clock on the visitor's machine, and recomputing
// from the timestamp (not counting ticks) self-corrects after the browser
// throttles timers in a background tab.
function initUptime() {
    const el = document.querySelector('[data-uptime]');

    if (!el || el.dataset.uptime !== '1') {
        return;
    }

    const bootedAt = Number(el.dataset.bootedAt ?? 0);
    const renderedAt = Number(el.dataset.renderedAt ?? 0);

    if (!Number.isFinite(bootedAt) || bootedAt <= 0) {
        return;
    }

    const offset = renderedAt > 0
        ? renderedAt - Math.floor(Date.now() / 1000)
        : 0;

    const tick = () => {
        const now = Math.floor(Date.now() / 1000) + offset;
        el.textContent = formatUptime(now - bootedAt);
    };

    tick();
    setInterval(tick, 1000);
}

function renderBarChart(canvas, series, { label, color, axisTitle }) {
    return loadCharts()
        .then(({ Chart }) => {
            new Chart(canvas, {
                type: 'bar',
                data: {
                    labels: tickValues(series),
                    datasets: [
                        {
                            label,
                            data: series.map((point) => point.count ?? point.total ?? 0),
                            backgroundColor: color,
                            borderRadius: 3,
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: (ctx) => `${ctx.parsed.y} ${axisTitle}`,
                            },
                        },
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: series.length > 10
                                ? { maxRotation: 0, autoSkip: true, maxTicksLimit: 10 }
                                : {},
                        },
                        y: {
                            beginAtZero: true,
                            ticks: { precision: 0 },
                            grid: { color: GRID },
                        },
                    },
                },
            });
        })
        .catch(() => {
            // Chart failed to load — the server-rendered fallback stands.
        });
}

function renderStaticCharts() {
    const growth = document.querySelector('[data-growth-canvas]');
    const growthSeries = parseSeries(growth);

    if (growth && growthSeries && growthSeries.some((point) => point.count > 0)) {
        renderBarChart(growth, growthSeries, {
            label: 'Students registered',
            color: 'rgba(16, 185, 129, 0.6)',
            axisTitle: 'students',
        });
    }

    const onboarding = document.querySelector('[data-onboarding-canvas]');
    const onboardingSeries = parseSeries(onboarding);

    if (onboarding && onboardingSeries && onboardingSeries.some((point) => point.count > 0)) {
        renderBarChart(onboarding, onboardingSeries, {
            label: 'Quick checks finished',
            color: 'rgba(2, 132, 199, 0.6)',
            axisTitle: 'quick checks',
        });
    }
}

async function fetchMetric(href) {
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

function initExplorer(canvas) {
    const tiles = Array.from(document.querySelectorAll('[data-metric-tile]'));
    const subtitle = document.querySelector('[data-metric-subtitle]');
    const hint = document.querySelector('[data-metric-hint]');
    const empty = document.querySelector('[data-metric-empty]');
    const wrap = document.querySelector('[data-metric-chart-wrap]');
    const chartError = document.querySelector('[data-metric-chart-error]');
    const detail = document.querySelector('[data-metric-detail]');
    const detailLabel = detail?.querySelector('[data-metric-detail-label]');

    let current = canvas.dataset.metric ?? '';
    let chart = null;
    let inFlight = false;
    let pending = null;

    // Tiles stay interactive even when the chart is still loading or has
    // failed: handlers are bound below, synchronously, before any Chart.js
    // work starts. A broken chart must never turn tiles back into links.
    const paint = (points, metric) => {
        if (!chart) {
            return;
        }

        chart.data = lineData(points, metric);
        chart.options = lineOptions(points, metric);
        chart.update();
    };

    const apply = (payload, href, push) => {
        const active = tiles.find((tile) => tile.dataset.metricTile === payload.metric);

        tiles.forEach((tile) => {
            const isActive = tile === active;
            tile.toggleAttribute('data-metric-active', isActive);
            tile.setAttribute('aria-current', isActive ? 'true' : 'false');
        });

        if (active) {
            const value = active.querySelector('[data-metric-value]');
            if (value) {
                value.textContent = payload.headline;
            }
        }

        if (subtitle) {
            subtitle.textContent = payload.subtitle;
        }

        if (hint) {
            hint.textContent = payload.hint;
        }

        if (detail) {
            if (detailLabel && payload.detail_label) {
                detailLabel.textContent = payload.detail_label;
            }

            // Sum/avg tiles with a zero headline have nothing to review —
            // the link would be a dead end, so it hides until a count lands.
            const total = parseFloat(String(payload.headline).replace(/,/g, ''));
            const show = Boolean(payload.detail_url) && Number.isFinite(total) && total > 0;

            if (payload.detail_url) {
                detail.setAttribute('href', payload.detail_url);
            }

            detail.classList.toggle('hidden', !show);
        }

        if (empty) {
            empty.classList.toggle('hidden', payload.points.length > 0);
        }

        const nextMetric = {
            label: payload.label,
            unit: payload.unit,
            precision: payload.precision,
            zero: payload.zero,
        };

        canvas.dataset.label = nextMetric.label;
        canvas.dataset.unit = nextMetric.unit;
        canvas.dataset.precision = String(nextMetric.precision);
        canvas.dataset.zero = nextMetric.zero ? '1' : '0';

        pending = { points: payload.points, metric: nextMetric };
        paint(payload.points, nextMetric);

        canvas.dataset.metric = payload.metric;
        current = payload.metric;

        if (push && href) {
            window.history.pushState({ metric: payload.metric }, '', href);
        }
    };

    // Tiles link to the dashboard HTML page (the JS-off path). The JSON for
    // the redraw comes from the series endpoint, which shares the tile's
    // query string (metric + range) - fetching the href itself would return
    // HTML, fail to parse, and fall back to a full page reload.
    const seriesUrlFor = (href) => {
        const endpoint = canvas.dataset.seriesEndpoint;

        if (!endpoint) {
            return href;
        }

        const url = new URL(href, window.location.origin);

        return `${endpoint}?${url.searchParams.toString()}`;
    };

    const load = async (href, push) => {
        if (inFlight) {
            return;
        }

        inFlight = true;
        wrap?.classList.add('opacity-50', 'pointer-events-none');

        const payload = await fetchMetric(seriesUrlFor(href));

        inFlight = false;
        wrap?.classList.remove('opacity-50', 'pointer-events-none');

        if (!payload) {
            window.showToast?.('Could not refresh the chart, reloading.');
            window.location.href = href;

            return;
        }

        apply(payload, href, push);
    };

    tiles.forEach((tile) => {
        tile.addEventListener('click', (event) => {
            if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                return;
            }

            const href = tile.getAttribute('href');
            const metric = tile.dataset.metricTile;

            if (!href || metric === current) {
                event.preventDefault();

                return;
            }

            event.preventDefault();
            load(href, true);
        });
    });

    window.addEventListener('popstate', () => {
        const params = new URLSearchParams(window.location.search);
        const metric = params.get('metric');

        if (metric && metric !== current) {
            load(window.location.pathname + window.location.search, false);
        }
    });

    loadCharts()
        .then(({ Chart }) => {
            const initial = pending ?? { points: parseSeries(canvas) ?? [], metric: readMetric(canvas) };

            chart = new Chart(canvas, {
                type: 'line',
                data: lineData(initial.points, initial.metric),
                options: lineOptions(initial.points, initial.metric),
            });
        })
        .catch((error) => {
            console.error('[alice] explorer chart failed to load', error);

            if (chartError) {
                chartError.classList.remove('hidden');
            }
        });
}

export default function init() {
    // The bundler can emit two copies of this module on the dashboard (the
    // page loader via data-page AND the admin guard in app.js), so a
    // module-local flag cannot dedupe them - each copy would have its own.
    // A window flag is shared by every copy: the second init is a no-op
    // instead of a second Chart on the same canvas.
    if (window.__aliceDashboardInited) {
        return;
    }

    window.__aliceDashboardInited = true;

    initUptime();
    renderStaticCharts();

    const metricCanvas = document.querySelector('[data-metric-canvas]');

    if (metricCanvas) {
        initExplorer(metricCanvas);
    }
}
