/**
 * Admin status page tabs (ALICE admin side).
 *
 * Splits the status page into Overview / External / Traffic / Logs sections
 * shown/hidden client-side, driven by a segmented tab bar. The active tab is
 * persisted in the URL hash (e.g. #external) so back/forward and deep links
 * work. No-JS fallback: all sections render and are visible in order (the
 * JS simply hides non-active ones).
 */

const TABS = ['overview', 'external', 'dependencies', 'traffic', 'logs'];

function currentFromHash() {
    const hash = window.location.hash.replace('#', '').toLowerCase();
    return TABS.includes(hash) ? hash : 'overview';
}

function show(name) {
    if (!TABS.includes(name)) {
        return;
    }

    document.querySelectorAll('[data-status-tab]').forEach((tab) => {
        const isActive = tab.dataset.statusTab === name;
        tab.setAttribute('aria-current', isActive ? 'true' : 'false');
        tab.classList.toggle('bg-[#2D231E]', isActive);
        tab.classList.toggle('text-[#FAF6EE]', isActive);
        tab.classList.toggle('bg-white', !isActive);
        tab.classList.toggle('text-[#2D231E]', !isActive);
    });

    document.querySelectorAll('[data-status-panel]').forEach((panel) => {
        const isActive = panel.dataset.statusPanel === name;
        panel.classList.toggle('hidden', !isActive);
    });

    if (window.location.hash !== `#${name}`) {
        window.history.replaceState(null, '', `#${name}`);
    }
}

export default function init() {
    const root = document.querySelector('[data-status-tabs]');

    if (!root) {
        return;
    }

    root.addEventListener('click', (event) => {
        const tab = event.target.closest('[data-status-tab]');

        if (!tab) {
            return;
        }

        event.preventDefault();
        show(tab.dataset.statusTab);
    });

    window.addEventListener('hashchange', () => {
        show(currentFromHash());
    });

    // Initial state: honour the hash on first load.
    show(currentFromHash());
}
