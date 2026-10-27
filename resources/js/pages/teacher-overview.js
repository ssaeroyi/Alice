/**
 * Teacher Overview dashboard enhancement (ALICE teacher side).
 *
 * Two jobs: the class-performance explorer (tile click fetches a series and
 * redraws a shared chart, switching between line and bar per metric) and the
 * reading-levels donut with its clickable legend. Chart.js comes through the
 * shared lazy loader (resources/js/charts.js) so the global bundle stays lean.
 * If a chart fails to init, the tiles and lists stay server-rendered.
 */

import { loadCharts } from '../charts.js';

const GRID = 'rgba(45, 35, 30, 0.08)';

function parseJson(value, fallback) {
    if (!value) {
        return fallback;
    }

    try {
        return JSON.parse(value);
    } catch {
        return fallback;
    }
}

function shortDate(date) {
    const [, month, day] = date.split('-');
    return `${month}/${day}`;
}

function formatValue(value, precision) {
    return precision > 0 ? Number(value).toFixed(precision) : Math.round(value).toLocaleString();
}

function chartOptions(points, metric, type) {
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
        ...(type === 'bar'
            ? { animation: { duration: 250 } }
            : {}),
    };
}

function chartData(points, metric, type) {
    const labels = points.map((point) => shortDate(point.date));

    if (type === 'bar') {
        return {
            labels,
            datasets: [
                {
                    label: metric.label,
                    data: points.map((point) => point.count),
                    backgroundColor: 'rgba(56, 189, 248, 0.55)',
                    borderColor: 'rgba(56, 189, 248, 1)',
                    borderWidth: 1,
                    borderRadius: 3,
                },
            ],
        };
    }

    return {
        labels,
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

function readMetric(canvas) {
    return {
        label: canvas.dataset.label ?? '',
        unit: canvas.dataset.unit ?? '',
        precision: Number(canvas.dataset.precision ?? 0),
        zero: canvas.dataset.zero !== '0',
        chart: canvas.dataset.chart === 'bar' ? 'bar' : 'line',
    };
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

    // Chart only exists once the lazy chunk resolves, and rebuild() runs from
    // tile clicks too, so the constructor lives in this closure (not inside
    // the .then callback) and a click that lands before the chunk resolves is
    // queued instead of throwing "Chart is not defined".
    let ChartCtor = null;
    let pending = null;

    // Chart.js instances are locked to a type, so switching between a line
    // metric (avg score) and a bar metric (counts) means a fresh instance.
    const rebuild = (points, metric) => {
        if (!ChartCtor) {
            pending = { points, metric };

            return;
        }

        if (chart) {
            chart.destroy();
            chart = null;
        }

        chart = new ChartCtor(canvas, {
            type: metric.chart,
            data: chartData(points, metric, metric.chart),
            options: chartOptions(points, metric, metric.chart),
        });
    };

    const paint = (points, metric) => {
        if (!chart || chart.config.type !== metric.chart) {
            rebuild(points, metric);

            return;
        }

        chart.data = chartData(points, metric, metric.chart);
        chart.options = chartOptions(points, metric, metric.chart);
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

            // Sum/avg tiles with a zero headline have nothing to review, so
            // the link stays hidden until a count lands.
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
            chart: payload.chart === 'bar' ? 'bar' : 'line',
        };

        canvas.dataset.label = nextMetric.label;
        canvas.dataset.unit = nextMetric.unit;
        canvas.dataset.precision = String(nextMetric.precision);
        canvas.dataset.zero = nextMetric.zero ? '1' : '0';
        canvas.dataset.chart = nextMetric.chart;

        paint(payload.points, nextMetric);

        canvas.dataset.metric = payload.metric;
        current = payload.metric;

        if (push && href) {
            window.history.pushState({ metric: payload.metric }, '', href);
        }
    };

    // Tiles link to the dashboard HTML page (the JS-off path). The JSON for
    // the redraw comes from the series endpoint, which shares the tile's
    // query string (metric + range).
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
            ChartCtor = Chart;

            // A tile click that arrived while the chunk was loading already
            // queued the newest payload; replay it so the chart matches.
            if (pending) {
                const { points, metric } = pending;
                pending = null;
                rebuild(points, metric);

                return;
            }

            const points = parseJson(canvas.dataset.series, []);
            const metric = readMetric(canvas);

            rebuild(points, metric);
        })
        .catch((error) => {
            console.error('[alice] teacher explorer chart failed to load', error);

            if (chartError) {
                chartError.classList.remove('hidden');
            }
        });
}

const DONUT_COLORS = {
    independent: 'rgba(52, 211, 153, 0.85)',
    instructional: 'rgba(251, 191, 36, 0.85)',
    frustration: 'rgba(251, 146, 60, 0.85)',
    critical: 'rgba(251, 113, 133, 0.85)',
};

function initReadingDonut(canvas) {
    const rows = parseJson(canvas.dataset.reading, []);

    if (rows.length === 0) {
        return;
    }

    // Guard against a legend click racing the lazy Chart.js load: remember
    // the pick and navigate after the instance exists.
    let picked = null;

    loadCharts()
        .then(({ Chart }) => {
            new Chart(canvas, {
                type: 'doughnut',
                data: {
                    labels: rows.map((row) => row.label),
                    datasets: [
                        {
                            data: rows.map((row) => row.count),
                            backgroundColor: rows.map((row) => DONUT_COLORS[row.key] ?? 'rgba(148, 163, 184, 0.85)'),
                            borderColor: '#FFFFFF',
                            borderWidth: 2,
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '62%',
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: (ctx) => {
                                    const row = rows[ctx.dataIndex];
                                    const total = rows.reduce((sum, item) => sum + item.count, 0);
                                    const pct = total > 0 ? Math.round((row.count / total) * 100) : 0;

                                    return `${row.label}: ${row.count} students (${pct}%)`;
                                },
                            },
                        },
                    },
                    onClick: (event, elements) => {
                        if (elements.length > 0) {
                            window.location.href = rows[elements[0].index].href;
                        }
                    },
                },
            });

            if (picked !== null) {
                window.location.href = rows[picked].href;
            }
        })
        .catch(() => {
            // Chart failed to load. The legend links still navigate.
            if (picked !== null) {
                window.location.href = rows[picked].href;
            }
        });
}

const GRADE_COLORS = ['#34D399', '#38BDF8', '#FBBF24', '#A78BFA', '#FB7185', '#2DD4BF', '#F472B6'];

/** Reading-growth line: weekly average quiz score over the full history. */
function initGrowthLine(canvas) {
    const rows = parseJson(canvas.dataset.growth, []);

    if (rows.length === 0) {
        return;
    }

    loadCharts()
        .then(({ Chart }) => {
            new Chart(canvas, {
                type: 'line',
                data: {
                    labels: rows.map((row) => row.date),
                    datasets: [
                        {
                            label: 'Avg quiz score',
                            data: rows.map((row) => row.count),
                            borderColor: '#34D399',
                            backgroundColor: 'rgba(52, 211, 153, 0.15)',
                            fill: true,
                            tension: 0.3,
                            pointRadius: rows.length > 40 ? 0 : 3,
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { suggestedMin: 0, suggestedMax: 100 },
                        x: { ticks: { maxTicksLimit: 8, font: { size: 10 } } },
                    },
                },
            });
        })
        .catch(() => {
            // The compare statement still carries the headline.
        });
}

/** Grade-distribution doughnut: same mechanics as the reading donut, click a
 *  slice opens the roster filtered to that grade. */
function initGradeDonut(canvas) {
    const rows = parseJson(canvas.dataset.grade, []);

    if (rows.length === 0) {
        return;
    }

    loadCharts()
        .then(({ Chart }) => {
            new Chart(canvas, {
                type: 'doughnut',
                data: {
                    labels: rows.map((row) => 'Grade ' + row.grade),
                    datasets: [
                        {
                            data: rows.map((row) => row.total),
                            backgroundColor: rows.map((row) => row.color),
                            borderColor: '#FFFFFF',
                            borderWidth: 2,
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '62%',
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: (ctx) => {
                                    const row = rows[ctx.dataIndex];
                                    const total = rows.reduce((sum, item) => sum + item.total, 0);
                                    const pct = total > 0 ? Math.round((row.total / total) * 100) : 0;

                                    return `Grade ${row.grade}: ${row.total} students (${pct}%)`;
                                },
                            },
                        },
                    },
                    onClick: (event, elements) => {
                        if (elements.length > 0) {
                            window.location.href = rows[elements[0].index].href;
                        }
                    },
                },
            });
        })
        .catch(() => {
            // Legend links still navigate without the chart.
        });
}

export default function init() {
    // The page loader can run twice on a chunk retry, so a window flag keeps
    // a second init from stacking a second Chart on the same canvas.
    if (window.__aliceTeacherOverviewInited) {
        return;
    }
    window.__aliceTeacherOverviewInited = true;

    const metricCanvas = document.querySelector('[data-metric-canvas]');

    if (metricCanvas) {
        initExplorer(metricCanvas);
    }

    const readingCanvas = document.querySelector('[data-reading-canvas]');

    if (readingCanvas && readingCanvas.dataset.reading) {
        initReadingDonut(readingCanvas);
    }

    const gradeCanvas = document.querySelector('[data-grade-canvas]');

    if (gradeCanvas && gradeCanvas.dataset.grade) {
        initGradeDonut(gradeCanvas);
    }

    const growthCanvas = document.querySelector('[data-growth-line-canvas]');

    if (growthCanvas && growthCanvas.dataset.growth) {
        initGrowthLine(growthCanvas);
    }
}
