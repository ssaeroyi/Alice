const DARK_KEY = 'alice-dark-mode';

function current() {
    return document.documentElement.classList.contains('dark');
}

function apply(dark, persist = true) {
    document.documentElement.classList.toggle('dark', dark);
    if (persist) {
        try {
            localStorage.setItem(DARK_KEY, dark ? '1' : '0');
        } catch (_) { /* storage unavailable — still usable for the session */ }
    }
    // Sync the state of every theme toggle/switch on the page.
    document.querySelectorAll('[data-theme-toggle]').forEach((btn) => {
        const active = dark;
        btn.classList.toggle('theme-dark-active', active);
        if (btn.getAttribute('role') === 'switch') {
            // Switch: aria-checked + a slide-on modifier on the track so the
            // thumb moves and the track fills amber when dark is active.
            btn.setAttribute('aria-checked', String(active));
            btn.setAttribute('aria-label', active ? 'Dark mode on. Switch to light mode' : 'Dark mode off. Switch to dark mode');
            const track = btn.querySelector('.theme-switch');
            if (track) track.classList.toggle('theme-switch-on', active);
            // Swap the sun/moon glyph inside the moving thumb: sun in light,
            // moon in dark.
            btn.querySelectorAll('[data-switch-icon]').forEach((g) => {
                const isSun = g.getAttribute('data-switch-icon') === 'sun';
                g.style.display = active ? (isSun ? 'none' : '') : (isSun ? '' : 'none');
            });
        } else {
            // Push-button toggle (dropdown / immersive): aria-pressed + icon swap.
            btn.setAttribute('aria-pressed', String(active));
            btn.setAttribute('aria-label', active ? 'Switch to light mode' : 'Switch to dark mode');
            const sun = btn.querySelector('[data-theme-icon="sun"]');
            const moon = btn.querySelector('[data-theme-icon="moon"]');
            if (sun) sun.hidden = active;
            if (moon) moon.hidden = !active;
        }
    });
}

// Set the initial state before first paint. Prefers an explicit stored choice
// and only falls back to the OS scheme when nothing has been chosen yet.
export function initTheme() {
    let dark = false;
    try {
        const stored = localStorage.getItem(DARK_KEY);
        dark = stored === null
            ? (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches)
            : stored === '1';
    } catch (_) {
        dark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
    }
    apply(dark, false);
}

export function initThemeToggles(root = document) {
    root.querySelectorAll('[data-theme-toggle]').forEach((btn) => {
        btn.addEventListener('click', () => apply(!current()));
    });
    // Reflect the current (already-applied) state on the toggle control.
    apply(current(), false);
}