let drawer = null;
let backdrop = null;
let panel = null;
let strip = null;
let summary = null;
let toggle = null;

const ensureInit = () => {
    if (drawer && toggle) {
        return true;
    }

    drawer = document.querySelector('[data-insights-drawer]');
    strip = document.querySelector('[data-insights-strip]');
    summary = document.querySelector('[data-insights-summary]');
    toggle = document.querySelector('[data-insights-toggle]');

    if (!drawer || !toggle) {
        return false;
    }

    backdrop = drawer.querySelector('[data-insights-backdrop]');
    panel = drawer.querySelector('[data-insights-panel]');

    if (panel) {
        panel.tabIndex = -1;
    }

    return true;
};

export function updateInsightsSummary() {
    if (!ensureInit() || !strip || !summary) {
        return;
    }

    const level = document.querySelector('[data-rl-level="content"]');
    const grammar = document.querySelector('[data-grammar-summary="content"]');

    const parts = [];

    if (level && level.textContent.trim() !== '') {
        parts.push(level.textContent.trim());
    }

    if (grammar && grammar.textContent.trim() !== '') {
        parts.push('Grammar: ' + grammar.textContent.trim().split(' — ')[0]);
    }

    summary.textContent = parts.length > 0 ? ' · ' + parts.join(' · ') : '';
    strip.classList.toggle('hidden', parts.length === 0);
}

const isOpen = () => toggle.getAttribute('aria-expanded') === 'true';

function openInsights() {
    if (!ensureInit()) {
        return;
    }

    drawer.classList.remove('pointer-events-none');
    backdrop.classList.add('opacity-100', 'pointer-events-auto');
    panel.classList.remove('translate-x-full');
    document.body.style.overflow = 'hidden';
    toggle.setAttribute('aria-expanded', 'true');

    panel.focus({ preventScroll: true });
}

function closeInsights() {
    if (!ensureInit()) {
        return;
    }

    drawer.classList.add('pointer-events-none');
    backdrop.classList.remove('opacity-100', 'pointer-events-auto');
    panel.classList.add('translate-x-full');
    document.body.style.overflow = '';
    toggle.setAttribute('aria-expanded', 'false');
}

export function initStoryInsights(root = document) {
    if (!ensureInit()) {
        return;
    }

    root.addEventListener('click', (event) => {
        if (event.target.closest('[data-insights-toggle]')) {
            if (isOpen()) {
                closeInsights();
            } else {
                openInsights();
            }

            return;
        }

        if (event.target.closest('[data-insights-close]') || event.target === backdrop) {
            closeInsights();
        }
    });

    root.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && isOpen()) {
            closeInsights();
        }
    });
}
