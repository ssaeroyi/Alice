/**
 * Student Hub STATS interactivity (HUB polish): drives the chart's time-range
 * toggle (Today / This Week / Last Week / All Time) and the interactive stamp
 * calendar (Prev / Next / Today) entirely client-side. Both re-fetch a single
 * JSON feed (GET /hub/stats?days=&month=) and re-render in place, so the
 * student keeps their place on the tab — no page reload, matching the rest of
 * the hub tabs.
 *
 * Markup contract:
 *   <div data-hub-stats>
 *     ...chart...
 *       <button data-interval="today|thisweek|lastweek|all">...</button>
 *       <div data-chart-series></div>      // bars re-rendered here
 *       <div data-chart-empty></div>       // shown when no activity
 *       <span data-chart-title>Last 7 Days</span>
 *     ...Activity History (multi-month line)...
 *       <button data-history-metric="overall|reading_minutes|quizzes|games|drills">..</button>
 *       <span data-history-title>...<span>Overall activity</span> per month</span>
 *       <div data-monthly-empty></div>     // shown when every month's overall is 0
 *       <div data-monthly-chart>           // line chart re-rendered here
 *         <canvas data-monthly-canvas></canvas>
 *       </div>
 *     ...calendar...
 *       <button data-cal-nav data-cal-action="prev|next|today">...</button>
 *       <span data-cal-title>August 2026</span>
 *       <span data-cal-count>12 stamped</span>
 *       <div data-cal-grid></div>          // 7-col grid re-rendered here
 *   </div>
 */
import { loadCharts } from '../charts.js';
import { openModal } from './modal';

const MODES = ['today', 'thisweek', 'lastweek', 'all'];

const MODE_TITLES = {
    today: 'Today',
    thisweek: 'This Week',
    lastweek: 'Last Week',
    all: 'All Time — by month',
};
const WEEKDAYS = ['S', 'M', 'T', 'W', 'T', 'F', 'S'];

// Matches the blade legend chips: reading emerald-600, quiz sky-500,
// games amber-400, drills violet-400.
const CATEGORIES = [
    { key: 'reading_seconds', label: 'Reading', color: '#059669' },
    { key: 'quizzes', label: 'Quizzes', color: '#0EA5E9' },
    { key: 'games', label: 'Games', color: '#FBBF24' },
    { key: 'drills', label: 'Drills', color: '#A78BFA' },
];

const ink = '#2D231E';

// Metric selector for the Activity History line graph. Keys map to array
// members of HubStats::monthlySeries(); OVERALL is the combined volume
// (= reading minutes + quizzes + games + drills) so its Y axis stays the
// familiar "activity" meaning students already know.
const HISTORY_METRICS = {
    overall: {
        key: 'overall',
        label: 'Overall activity',
        unit: 'pt',
        color: '#2D231E',
        chipClass: 'text-[#2D231E]',
        chipActive: false,
    },
    reading_minutes: {
        key: 'reading_minutes',
        label: 'Reading minutes',
        unit: 'min read',
        color: '#059669',
        chipClass: 'text-emerald-600',
    },
    quizzes: {
        key: 'quizzes',
        label: 'Quizzes',
        unit: 'quiz',
        color: '#0EA5E9',
        chipClass: 'text-sky-500',
    },
    games: {
        key: 'games',
        label: 'Games',
        unit: 'round',
        color: '#FBBF24',
        chipClass: 'text-amber-500',
    },
    drills: {
        key: 'drills',
        label: 'Drills',
        unit: 'drill',
        color: '#8B5CF6',
        chipClass: 'text-violet-500',
    },
};

function prefersReducedMotion() {
    return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

// Chart.js is loaded lazily (see charts.js). This holds the resolved class so
// renderChart/renderMonthly can construct instances after init awaits it.
let jsChart = null;

async function ensureChart() {
    if (!jsChart) {
        const mod = await loadCharts();
        jsChart = mod.default ?? mod;
    }
    return jsChart;
}

/**
 * Hub "Edit" dialog for the daily reading goal (D). Owns the
 * /hub/reading-goal PATCH (the editor moved here from Settings when Settings'
 * DAILY GOALS section was removed, so the hub is the sole goal editor).
 * Range slider over config('gamification.reading_goal_*'); 0 = no goal.
 */
export function initHubReadingGoal() {
    const slider = document.querySelector('[data-hub-reading-goal-slider]');
    const output = document.querySelector('[data-hub-reading-goal-output]');
    const unit = document.querySelector('[data-hub-reading-goal-unit]');
    const saveButton = document.querySelector('[data-hub-save-reading-goal]');
    if (!slider || !output || !unit || !saveButton) return;

    const status = document.querySelector('[data-hub-reading-goal-status]');

    const render = (value) => {
        const numeric = Number(value);
        output.textContent = numeric === 0 ? 'OFF' : String(numeric);
        unit.textContent = numeric === 0 ? 'no goal' : 'min / day';
    };

    const showStatus = (message) => {
        if (!status) return;
        status.textContent = message;
        status.classList.remove('hidden');
        window.clearTimeout(window.__hubReadingGoalStatusTimer);
        window.__hubReadingGoalStatusTimer = window.setTimeout(() => status.classList.add('hidden'), 2600);
    };

    slider.addEventListener('input', () => {
        render(slider.value);
    });

    saveButton.addEventListener('click', async () => {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
        saveButton.disabled = true;

        try {
            const response = await fetch('/hub/reading-goal', {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify({ goal: Number(slider.value) }),
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const data = await response.json();
            const numericGoal = Number(data.goal ?? 0);

            const goalText = document.querySelector('[data-hub-reading-goal-text]');
            if (goalText) goalText.textContent = numericGoal === 0 ? 'Off' : String(numericGoal);

            showStatus(`Saved! Goal is ${numericGoal === 0 ? 'off' : `${numericGoal} minutes a day.`}`);

            document.querySelector('[data-modal-close]')?.click();

            // The goal also drives the HOME progress card; a reload keeps the
            // snapshot + chart singular. Only reload when it changed.
            window.location.reload();
        } catch {
            saveButton.textContent = 'TRY AGAIN';
        } finally {
            saveButton.disabled = false;
            window.setTimeout(() => {
                if (saveButton.textContent !== 'TRY AGAIN') saveButton.textContent = 'Save';
            }, 2000);
        }
    });
}

/**
 * Hub "Edit" dialog for the daily study goal. Shares the /study/goal PATCH
 * with the Study dashboard's slider so both stay in step.
 */
export function initHubStudyGoal() {
    const slider = document.querySelector('[data-hub-study-goal-slider]');
    const output = document.querySelector('[data-hub-study-goal-output]');
    const saveButton = document.querySelector('[data-hub-save-study-goal]');
    if (!slider || !output || !saveButton) return;

    const status = document.querySelector('[data-hub-study-goal-status]');

    const showStatus = (message) => {
        if (!status) return;
        status.textContent = message;
        status.classList.remove('hidden');
        window.clearTimeout(window.__hubStudyGoalStatusTimer);
        window.__hubStudyGoalStatusTimer = window.setTimeout(() => status.classList.add('hidden'), 2600);
    };

    const formatNumber = (value) => new Intl.NumberFormat('en-US').format(value);

    slider.addEventListener('input', () => {
        output.textContent = formatNumber(slider.value);
    });

    saveButton.addEventListener('click', async () => {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
        saveButton.disabled = true;

        try {
            const response = await fetch('/study/goal', {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify({ goal: Number(slider.value) }),
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const data = await response.json();
            const numericGoal = Number(data.goal ?? slider.value);

            output.textContent = formatNumber(numericGoal);
            showStatus(`Saved! Goal is ${formatNumber(numericGoal)} words a day.`);

            document.querySelector('[data-modal-close]')?.click();

            // The goal also drives the HOME progress card; a reload keeps the
            // snapshot + chart singular. Only reload when it changed.
            window.location.reload();
        } catch {
            saveButton.textContent = 'TRY AGAIN';
        } finally {
            saveButton.disabled = false;
            window.setTimeout(() => {
                if (saveButton.textContent !== 'TRY AGAIN') saveButton.textContent = 'Save';
            }, 2000);
        }
    });
}

export async function initHubStats() {
    const root = document.querySelector('[data-hub-stats]');
    if (!root) return;

    // Chart.js must be ready before any chart can be drawn. Awaiting here (before
    // the initial snapshot render and before the toggles attach) guarantees the
    // chart constructors below never see a null Chart.
    await ensureChart();

    const chartCanvas = root.querySelector('[data-chart-canvas]');
    const chartEmpty = root.querySelector('[data-chart-empty]');
    const chartTitle = root.querySelector('[data-chart-title]');
    const calGrid = root.querySelector('[data-cal-grid]');
    const calTitle = root.querySelector('[data-cal-title]');
    const calCount = root.querySelector('[data-cal-count]');
    const monthlyChart = root.querySelector('[data-monthly-chart]');
    const monthlyCanvas = root.querySelector('[data-monthly-canvas]');
    const monthlyEmpty = root.querySelector('[data-monthly-empty]');

    const params = new URLSearchParams(window.location.search);

    const state = {
        mode: MODES.includes(String(params.get('days'))) ? String(params.get('days')) : 'thisweek',
        year: Number(params.get('month')?.slice(0, 4)) || new Date().getFullYear(),
        month: Number(params.get('month')?.slice(5, 7)) || new Date().getMonth() + 1,
    };

    let inFlight = null;
    let chart = null;

    const monthParam = () =>
        `${state.year}-${String(state.month).padStart(2, '0')}`;

    const disableNav = (disabled) => {
        root.querySelectorAll('[data-interval], [data-cal-nav]').forEach((b) => {
            b.disabled = disabled;
        });
    };

    const toggleActiveMode = () => {
        root.querySelectorAll('[data-interval]').forEach((b) => {
            const active = String(b.dataset.interval) === state.mode;
            b.classList.toggle('bg-amber-400', active);
            b.classList.toggle('bg-white', !active);
            b.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
    };

    const modeTitle = () => MODE_TITLES[state.mode] || MODE_TITLES.thisweek;

    const render = (data) => {
        if (data.mode) state.mode = data.mode;
        toggleActiveMode();

        renderChart(data);
        renderCalendar(data.calendar);
        wireHistoryChips(data.monthlySeries);
        renderMonthly(data.monthlySeries, monthlyMetric);
    };

    // Stamped-day dots: a tiny plugin draws a 2px dot under each bar so the
    // "today is stamped" cue survives the move from SSR divs to Chart.js.
    const stampDots = {
        id: 'stampDots',
        afterDraw(chr) {
            const days = chr.data?.days ?? [];
            const x = chr.scales.x;
            const bottom = chr.chartArea.bottom - 3;

            days.forEach((day, i) => {
                if (!day?.stamped) return;
                const px = x.getPixelForValue(i);
                const ctx = chr.ctx;
                ctx.save();
                ctx.fillStyle = '#10B981';
                ctx.strokeStyle = ink;
                ctx.lineWidth = 1;
                ctx.beginPath();
                ctx.arc(px, bottom, 3, 0, Math.PI * 2);
                ctx.fill();
                ctx.stroke();
                ctx.restore();
            });
        },
    };

    const renderChart = (data) => {
        if (!chartCanvas) return;

        if (chartTitle) chartTitle.textContent = modeTitle();

        // All Time drops the per-day bars and draws the monthly line from the
        // same monthlySeries that feeds the Activity History panel below.
        if (state.mode === 'all') {
            renderAllTime(data.monthlySeries || []);
            return;
        }

        const days = data.series.map((d) => {
            const date = new Date(d.date + 'T00:00:00');
            return { ...d, dow: WEEKDAYS[date.getDay()] };
        });

        const datasets = CATEGORIES.map((cat) => ({
            label: cat.label,
            data: days.map((d) =>
                cat.key === 'reading_seconds' ? Math.round(Math.max(d.reading_seconds, 0) / 60) : (d[cat.key] ?? 0),
            ),
            backgroundColor: cat.color,
            borderColor: ink,
            borderWidth: 1,
            borderRadius: 4,
            borderSkipped: 'bottom',
            stack: 'activity',
            maxBarThickness: 26,
        }));

        const hasActivity = days.some(
            (d) => d.reading_seconds > 0 || (d.quizzes ?? 0) > 0 || (d.games ?? 0) > 0 || (d.drills ?? 0) > 0,
        );
        if (chartEmpty) chartEmpty.classList.toggle('hidden', hasActivity);

        const reduceMotion = prefersReducedMotion();

        const config = {
            type: 'bar',
            data: {
                labels: days.map((d) => d.label),
                datasets,
            },
            plugins: [stampDots],
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: reduceMotion ? false : { duration: 350 },
                interaction: { mode: 'index', intersect: false },
                layout: { padding: { top: 4, bottom: 6 } },
                scales: {
                    x: {
                        stacked: true,
                        grid: { display: false },
                        border: { color: ink },
                        ticks: {
                            color: ink,
                            font: { size: 9, weight: 'bold' },
                            maxRotation: 0,
                            autoSkipPadding: 4,
                        },
                    },
                    y: {
                        stacked: true,
                        beginAtZero: true,
                        grid: { color: '#EDE4D8' },
                        border: { display: false },
                        ticks: {
                            color: '#2D231E',
                            font: { size: 9, weight: 'bold' },
                            precision: 0,
                            maxTicksLimit: 4,
                        },
                    },
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: ink,
                        borderColor: ink,
                        borderWidth: 1,
                        titleColor: '#FAF6EE',
                        bodyColor: '#FAF6EE',
                        padding: 8,
                        cornerRadius: 6,
                        titleFont: { size: 11, weight: 'bold' },
                        bodyFont: { size: 10 },
                        callbacks: {
                            label: (ctx) => `${ctx.dataset.label}: ${ctx.parsed.y}${ctx.dataset.label === 'Reading' ? ' min' : ''}`,
                            footer: (items) => {
                                const i = items[0]?.dataIndex ?? 0;
                                const day = days[i];
                                return day ? `${day.dow}, ${day.label}` : '';
                            },
                        },
                    },
                },
            },
        };

        if (chart) {
            chart.data = config.data;
            chart.options = config.options;
            chart.update();
        } else {
            chart = new jsChart(chartCanvas, config);
        }
    };

    // All Time reuses the monthly series as a line (the same visualization the
    // Activity History panel below uses), drawing it onto the chart panel's
    // canvas so toggling modes swaps bar <-> line in place.
    const renderAllTime = (series) => {
        const hasData = Array.isArray(series) && series.some((m) => Number(m.overall) > 0);
        if (chartEmpty) chartEmpty.classList.toggle('hidden', hasData);

        if (!hasData) {
            if (chart) { chart.destroy(); chart = null; }
            return;
        }

        const reduceMotion = prefersReducedMotion();
        const config = {
            type: 'line',
            data: {
                labels: series.map((m) => m.month),
                datasets: [{
                    label: 'Overall activity',
                    data: series.map((m) => Number(m.overall ?? 0)),
                    borderColor: ink,
                    borderWidth: 3,
                    pointBackgroundColor: '#6D28D9',
                    pointBorderColor: ink,
                    pointBorderWidth: 1,
                    pointRadius: 3,
                    pointHoverRadius: 5,
                    tension: 0,
                    fill: false,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: reduceMotion ? false : { duration: 350 },
                interaction: { mode: 'index', intersect: false },
                layout: { padding: { top: 4, bottom: 6 } },
                scales: {
                    x: {
                        grid: { display: false },
                        border: { color: ink },
                        ticks: {
                            color: ink,
                            font: { size: 9, weight: 'bold' },
                            maxRotation: 45,
                            minRotation: 45,
                            autoSkip: true,
                            maxTicksLimit: 8,
                        },
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: '#EDE4D8' },
                        border: { display: false },
                        ticks: {
                            color: '#2D231E',
                            font: { size: 9, weight: 'bold' },
                            precision: 0,
                            maxTicksLimit: 4,
                        },
                    },
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: ink,
                        borderColor: ink,
                        borderWidth: 1,
                        titleColor: '#FAF6EE',
                        bodyColor: '#FAF6EE',
                        padding: 8,
                        cornerRadius: 6,
                        titleFont: { size: 11, weight: 'bold' },
                        bodyFont: { size: 10 },
                        callbacks: {
                            label: (ctx) => {
                                const m = series[ctx.dataIndex] || {};
                                return [
                                    `Reading: ${m.reading_minutes} min`,
                                    `Quizzes: ${m.quizzes}`,
                                    `Games: ${m.games}`,
                                    `Drills: ${m.drills}`,
                                ];
                            },
                        },
                    },
                },
            },
        };

        if (chart) {
            chart.data = config.data;
            chart.options = config.options;
            chart.update();
        } else {
            chart = new jsChart(chartCanvas, config);
        }
    };

    let monthlyMetric = 'overall';

    const applyHistoryChips = () => {
        root.querySelectorAll('[data-history-metric]').forEach((b) => {
            const key = String(b.dataset.historyMetric);
            const active = key === monthlyMetric;
            b.classList.toggle('bg-amber-400', active);
            b.classList.toggle('bg-white', !active);
            b.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
    };

    const renderMonthly = (series, metric = monthlyMetric) => {
        if (!monthlyCanvas || !Array.isArray(series)) return;

        const meta = HISTORY_METRICS[metric] || HISTORY_METRICS.overall;
        monthlyMetric = metric;
        applyHistoryChips();

        const historyTitle = root.querySelector('[data-history-title]');
        if (historyTitle) {
            const titleSpan = historyTitle.querySelector('span');
            if (titleSpan) titleSpan.textContent = meta.label;
        }

        // A history is empty only when every month's overall volume is zero —
        // checking just one series would wrongly hide a reading-only student
        // whose first month happened to have no reading minutes but did quiz.
        const hasData = series.some((m) => Number(m.overall) > 0);
        if (monthlyEmpty) monthlyEmpty.classList.toggle('hidden', hasData);
        if (monthlyChart) monthlyChart.classList.toggle('hidden', !hasData);
        if (!hasData) return;

        const reduceMotion = prefersReducedMotion();
        const labels = series.map((m) => m.month);
        const values = series.map((m) => Number(m[meta.key] ?? 0));

        const config = {
            type: 'line',
            data: {
                labels,
                datasets: [{
                    label: meta.label,
                    data: values,
                    borderColor: meta.color,
                    borderWidth: 3,
                    pointBackgroundColor: meta.color,
                    pointBorderColor: ink,
                    pointBorderWidth: 1,
                    pointRadius: 3,
                    pointHoverRadius: 5,
                    tension: 0,
                    fill: false,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: reduceMotion ? false : { duration: 350 },
                interaction: { mode: 'index', intersect: false },
                layout: { padding: { top: 4, bottom: 6 } },
                scales: {
                    x: {
                        grid: { display: false },
                        border: { color: ink },
                        ticks: {
                            color: ink,
                            font: { size: 9, weight: 'bold' },
                            maxRotation: 45,
                            minRotation: 45,
                            autoSkip: true,
                            maxTicksLimit: 8,
                        },
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: '#EDE4D8' },
                        border: { display: false },
                        ticks: {
                            color: '#2D231E',
                            font: { size: 9, weight: 'bold' },
                            precision: 0,
                            maxTicksLimit: 4,
                        },
                    },
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: ink,
                        borderColor: ink,
                        borderWidth: 1,
                        titleColor: '#FAF6EE',
                        bodyColor: '#FAF6EE',
                        padding: 8,
                        cornerRadius: 6,
                        titleFont: { size: 11, weight: 'bold' },
                        bodyFont: { size: 10 },
                        callbacks: {
                            label: (ctx) => {
                                if (monthlyMetric === 'overall') {
                                    const m = series[ctx.dataIndex];
                                    return [
                                        `Reading: ${m.reading_minutes} min`,
                                        `Quizzes: ${m.quizzes}`,
                                        `Games: ${m.games}`,
                                        `Drills: ${m.drills}`,
                                    ];
                                }
                                return `${ctx.parsed.y} ${meta.unit === 'min read' ? 'min read' : meta.unit}`;
                            },
                        },
                    },
                },
            },
        };

        if (monthlyChart && monthlyChart.__chart) {
            const c = monthlyChart.__chart;
            c.data = config.data;
            c.options = config.options;
            c.update();
        } else if (monthlyChart) {
            monthlyChart.__chart = new jsChart(monthlyCanvas, config);
        }
    };

    const wireHistoryChips = (series) => {
        root.querySelectorAll('[data-history-metric]').forEach((b) => {
            b.addEventListener('click', () => {
                renderMonthly(series, String(b.dataset.historyMetric));
            });
        });
    };

    const renderCalendar = (cal) => {
        if (!calGrid) return;

        const todayStr = new Date().toISOString().slice(0, 10);

        const TIER_BG = { 1: 'bg-emerald-400', 2: 'bg-emerald-500', 3: 'bg-emerald-600', 4: 'bg-emerald-700' };
        const tapHint = 'cursor-pointer hover:shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] active:translate-y-[1px] focus-visible:outline-2 focus-visible:outline-amber-400 transition-shadow';

        const cell = (c) => {
            const detail = c.detail ? JSON.stringify(c.detail) : 'null';

            switch (c.state) {
                case 'pad':
                    return '<span class="min-h-[44px]"></span>';
                case 'stamped':
                    return `<button type="button" data-cal-day data-day="${c.day}" data-detail='${detail}' aria-label="Day ${c.day}, stamped. Open details." class="min-h-[44px] rounded-lg ${TIER_BG[c.detail?.tier] || 'bg-emerald-500'} border-2 border-[#2D231E] flex items-center justify-center font-pixel text-[10px] font-bold text-white ${tapHint}">${c.day}</button>`;
                case 'active':
                    return `<button type="button" data-cal-day data-day="${c.day}" data-detail='${detail}' aria-label="Day ${c.day}, active. Open details." class="min-h-[44px] rounded-lg bg-amber-100 border-2 border-[#2D231E] flex items-center justify-center font-pixel text-[10px] text-[#2D231E] ${tapHint}">${c.day}</button>`;
                case 'today':
                    return `<button type="button" data-cal-day data-day="${c.day}" data-detail='${detail}' aria-label="Day ${c.day}, today. Open details." class="min-h-[44px] rounded-lg border-2 border-[#2D231E] ring-2 ring-amber-400 flex items-center justify-center font-pixel text-[10px] font-bold text-[#2D231E] ${tapHint}">${c.day}</button>`;
                default:
                    return `<span class="min-h-[44px] rounded-lg border border-[#E6DCCF] text-[#2D231E] flex items-center justify-center font-pixel text-[10px]">${c.day}</span>`;
            }
        };

        const header = WEEKDAYS.map((d) => `<span class="font-pixel text-[8px] text-[#2D231E] uppercase">${d}</span>`).join('');
        const weeks = cal.weeks.map((week) => week.map(cell).join('')).join('');

        if (calTitle) calTitle.textContent = `${cal.month_name} ${cal.year}`;
        if (calCount) calCount.textContent = `${cal.stamped_count} stamped`;
        calGrid.innerHTML = header + weeks;

        root.querySelectorAll('[data-cal-nav]').forEach((btn) => {
            const action = btn.dataset.calAction;
            btn.classList.toggle('opacity-40', action === 'next' && isFutureMonth());
            btn.classList.toggle('opacity-100', action !== 'next' || !isFutureMonth());
        });
    };

    const isFutureMonth = () => {
        const now = new Date();
        return state.year > now.getFullYear()
            || (state.year === now.getFullYear() && state.month > now.getMonth() + 1);
    };

    const fetchStats = () => {
        if (inFlight) inFlight.abort();
        disableNav(true);
        root.classList.add('alice-busy');
        root.setAttribute('aria-busy', 'true');
        const controller = new AbortController();
        inFlight = controller;

        fetch(`/hub/stats?days=${state.mode}&month=${monthParam()}`, { signal: controller.signal })
            .then((res) => (res.ok ? res.json() : Promise.reject(new Error('stats')))
                .then((data) => {
                    inFlight = null;
                    disableNav(false);
                    root.classList.remove('alice-busy');
                    root.removeAttribute('aria-busy');
                    render(data);
                }))
            .catch((err) => {
                if (err.name !== 'AbortError') {
                    inFlight = null;
                    disableNav(false);
                    root.classList.remove('alice-busy');
                    root.removeAttribute('aria-busy');
                }
            });
    };

    // Draw once from the server-rendered snapshot so the chart and calendar
    // are never blank on first paint. Chart.js needs a finite parent height.
    const initial = root.querySelector('[data-initial-stats]');
    if (initial) {
        try {
            render(JSON.parse(initial.textContent));
        } catch {
            /* malformed snapshot -> rely on the toggle below */
        }
    }

    // A deep link like ?days=all renders the default snapshot first (all is
    // not part of the day shapes the server seeds); re-fetch so the highlighted
    // mode always matches the chart on screen.
    if (state.mode !== 'thisweek') {
        fetchStats();
    }

    root.querySelectorAll('[data-interval]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const mode = String(btn.dataset.interval);
            if (mode === state.mode) return;
            state.mode = mode;
            fetchStats();
        });
    });

    root.querySelectorAll('[data-cal-nav]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const action = btn.dataset.calAction;
            if (action === 'prev') {
                state.month = state.month === 1 ? 12 : state.month - 1;
                if (state.month === 12) state.year -= 1;
            } else if (action === 'next') {
                if (isFutureMonth()) return;
                state.month = state.month === 12 ? 1 : state.month + 1;
                if (state.month === 1) state.year += 1;
            } else {
                const now = new Date();
                state.year = now.getFullYear();
                state.month = now.getMonth() + 1;
            }
            fetchStats();
        });
    });

    // Tap a stamped/active/today cell -> pixel modal showing that day's
    // activity breakdown. Detail rides in the cell's data attribute (from the
    // same /hub/stats payload), so month-nav re-renders stay interactive with
    // no extra requests.
    const openDayDetail = (btn) => {
        const modal = document.getElementById('hub-day-detail');
        if (!modal) return;

        let detail = null;
        try {
            detail = JSON.parse(btn.dataset.detail || 'null');
        } catch {
            detail = null;
        }

        const date = new Date(state.year, state.month - 1, Number(btn.dataset.day));
        const dateLine = modal.querySelector('[data-day-date]');
        const status = modal.querySelector('[data-day-status]');
        const stats = modal.querySelector('[data-day-stats]');

        if (dateLine) {
            dateLine.textContent = date.toLocaleDateString(undefined, { month: 'long', day: 'numeric', year: 'numeric' });
        }

        if (status) {
            if (detail?.stamped_at) {
                status.textContent = `Stamped ${detail.stamped_at}`;
                status.className = 'inline-block -rotate-3 px-3 py-1.5 bg-emerald-500 text-white border-2 border-[#2D231E] rounded-lg font-pixel text-[10px] font-bold uppercase shadow-[3px_3px_0px_0px_rgba(45,35,30,1)]';
            } else if (detail) {
                status.textContent = 'Active day, no stamp yet';
                status.className = 'inline-block -rotate-3 px-3 py-1.5 bg-amber-400 text-[#2D231E] border-2 border-[#2D231E] rounded-lg font-pixel text-[10px] font-bold uppercase shadow-[3px_3px_0px_0px_rgba(45,35,30,1)]';
            } else {
                status.textContent = 'Nothing logged';
                status.className = 'inline-block px-3 py-1.5 bg-[#F4F0EA] text-[#B5A89C] border-2 border-dashed border-[#E6DCCF] rounded-lg font-pixel text-[10px] font-bold uppercase';
            }
        }

        if (stats) {
            const ICONS = {
                reads: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><path d="M12 6c-2-2-5-2-7-1v13c2-1 5-1 7 1 2-2 5-2 7-1V5c-2-1-5-1-7 1z"/><path d="M12 6v13"/></svg>',
                quizzes: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><path d="M9 9a3 3 0 1 1 4 2.8c-.9.4-1 1.2-1 2.2"/><path d="M12 18h.01"/></svg>',
                drills: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><rect x="4" y="7" width="13" height="13" rx="2"/><path d="M8 4h10a2 2 0 0 1 2 2v10"/></svg>',
                games: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><circle cx="12" cy="7" r="3"/><path d="M12 10v6M9 19h6"/></svg>',
            };

            const tile = (icon, color, value, label, sub) => `
                <div class="rounded-xl border-2 border-[#2D231E] bg-white p-3 flex flex-col items-center gap-1 shadow-[2px_2px_0px_0px_rgba(45,35,30,1)]">
                    <span class="w-8 h-8 flex items-center justify-center rounded-lg border-2 border-[#2D231E] ${color}">${icon}</span>
                    <span class="font-pixel text-base font-bold text-[#2D231E]">${value}</span>
                    <span class="font-pixel text-[8px] text-[#2D231E] uppercase">${label}</span>
                    ${sub ? `<span class="font-pixel text-[8px] font-bold text-emerald-600 uppercase">${sub}</span>` : ''}
                </div>`;

            const tiles = [];
            if (detail?.reads) tiles.push(tile(ICONS.reads, 'text-emerald-600 bg-emerald-50', detail.reads, 'Reads', detail.minutes ? `${detail.minutes} min` : ''));
            if (detail?.quizzes) tiles.push(tile(ICONS.quizzes, 'text-amber-600 bg-amber-50', detail.quizzes, 'Quizzes'));
            if (detail?.drills) tiles.push(tile(ICONS.drills, 'text-rose-500 bg-rose-50', detail.drills, 'Drills'));
            if (detail?.games) tiles.push(tile(ICONS.games, 'text-violet-500 bg-violet-50', detail.games, 'Games'));

            stats.innerHTML = tiles.length
                ? tiles.join('')
                : '<div class="col-span-2 rounded-xl border-2 border-dashed border-[#E6DCCF] p-4 text-center font-pixel text-[9px] text-[#B5A89C] uppercase">Nothing here yet</div>';
        }

        openModal(modal);
    };

    calGrid?.addEventListener('click', (event) => {
        const btn = event.target.closest('[data-cal-day]');
        if (btn) openDayDetail(btn);
    });

    // Activity chart <-> history: swap panes in place (no page reload). Both
    // panes come from the same /hub/stats payload, so this only toggles
    // visibility and re-sizes the canvas that just became visible.
    const activityToggle = root.querySelector('[data-activity-toggle]');
    const chartView = root.querySelector('[data-activity-view="chart"]');
    const historyView = root.querySelector('[data-activity-view="history"]');
    const historyMonths = root.querySelector('[data-history-months]');

    activityToggle?.addEventListener('click', () => {
        const showHistory = historyView?.classList.contains('hidden') ?? false;
        chartView?.classList.toggle('hidden', showHistory);
        historyView?.classList.toggle('hidden', !showHistory);
        historyMonths?.classList.toggle('hidden', !showHistory);

        if (showHistory) {
            monthlyChart?.__chart?.resize();
        } else {
            chart?.resize();
        }
    });

    toggleActiveMode();
}
