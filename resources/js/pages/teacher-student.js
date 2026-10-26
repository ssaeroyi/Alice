/**
 * Teacher Student detail page enhancement.
 *
 * Renders the student's quiz score history as a Chart.js scatter chart with
 * flag markers. Loaded via the shared page loader when data-page="teacher-student".
 * Chart.js is always obtained through loadCharts() (FRONTEND_GUIDELINES §10.6).
 */

import { loadCharts } from '../charts.js';

export default function init() {
    const canvas = document.querySelector('[data-quiz-history-canvas]');

    if (!canvas) {
        return;
    }

    let history;
    try {
        history = JSON.parse(canvas.dataset.history ?? '[]');
    } catch {
        history = [];
    }

    if (!Array.isArray(history) || history.length === 0) {
        return;
    }

    loadCharts()
        .then(({ Chart, PointElement }) => {
            const labels = history.map((a, i) => `${a.story} (${a.date})`);
            const scores = history.map((a) => a.score);
            const colors = history.map((a) =>
                a.flags
                    ? 'rgba(239, 68, 68, 0.85)'
                    : a.passed
                        ? 'rgba(16, 185, 129, 0.85)'
                        : 'rgba(234, 179, 8, 0.85)',
            );
            const radii = history.map((a) => (a.flags ? 8 : 5));
            const borderWidths = history.map((a) => (a.flags ? 3 : 1));
            const borderColors = history.map((a) => (a.flags ? 'rgba(239, 68, 68, 1)' : 'transparent'));

            new Chart(canvas, {
                type: 'bar',
                data: {
                    labels,
                    datasets: [
                        {
                            label: 'Score %',
                            data: scores,
                            backgroundColor: colors,
                            borderColor: borderColors,
                            borderWidth: borderWidths,
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
                                afterLabel: (ctx) => {
                                    const item = history[ctx.dataIndex];
                                    const parts = [item.passed ? 'Passed' : 'Not passed'];
                                    if (item.flags) {
                                        parts.push('⚠ Integrity notes available below');
                                    }
                                    return parts;
                                },
                            },
                        },
                    },
                    scales: {
                        x: {
                            ticks: { maxRotation: 45, font: { size: 10 } },
                            grid: { display: false },
                        },
                        y: {
                            min: 0,
                            max: 100,
                            title: { display: true, text: 'Score %' },
                            ticks: { callback: (v) => `${v}%` },
                        },
                    },
                },
            });
        })
        .catch(() => {
            // Chart.js failed to load — the server-rendered fallback stands.
        });
}