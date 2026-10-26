/**
 * Shared lazy Chart.js loader.
 *
 * Chart.js is heavy (charts, auto-registered controllers/plugins) and is only
 * needed on hub-style stats pages today. It used to be statically imported by
 * hub-stats.js, which dragged the whole library into the global app.js bundle
 * loaded on EVERY page. Instead we resolve it on demand and memoize the
 * result, so:
 *
 *   - student pages that never chart no longer download/parse Chart.js;
 *   - re-entering a chart page (or a future admin/teacher chart page) reuses
 *     the already-resolved Chart, never re-fetching or re-initialising.
 *
 * RULE (see FRONTEND_GUIDELINES.md): always obtain Chart.js through
 * loadCharts(); never add a new static `import Chart from 'chart.js/auto'`
 * anywhere in the global bundle, or the bloat returns.
 */

let chartPromise = null;

/**
 * Resolve Chart.js once and reuse the instance.
 * @returns {Promise<typeof import('chart.js/auto')>}
 */
export function loadCharts() {
    if (!chartPromise) {
        chartPromise = import('chart.js/auto');
    }
    return chartPromise;
}
