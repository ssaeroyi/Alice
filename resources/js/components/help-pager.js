/**
 * HOW ALICE WORKS help modal pager.
 *
 * The modal's body is split into `[data-help-page]` sections inside a
 * `[data-help-pager]` root (layouts/authenticated.blade.php). This shows one
 * page at a time and drives: the dot row, the Previous / Next buttons, the
 * final "Done" close action (last page only), ←/→ arrow keys while the modal
 * is open, and a reset to page 1 every time the modal opens.
 *
 * Shared by two modals: HOW ALICE WORKS (layouts/authenticated.blade.php)
 * and Weekly Leaderboards (student/mini-game.blade.php) — any future modal
 * wrapped in the same [data-help-pager] structure gets it for free.
 *
 * No-JS fallback: page 1 ships visible in the markup, the rest are hidden —
 * the modal simply stays on the welcome page.
 */
export function initHelpPager() {
    document.querySelectorAll('[data-help-pager]').forEach(setupPager);
}

function setupPager(root) {
    const modal = root.closest('[data-modal]');
    const pages = Array.from(root.querySelectorAll('[data-help-page]'));
    const dots = Array.from(root.querySelectorAll('[data-help-dot]'));
    const prevBtn = root.querySelector('[data-help-prev]');
    const nextBtn = root.querySelector('[data-help-next]');
    const doneBtn = root.querySelector('[data-help-done]');

    if (!modal || pages.length === 0) {
        return;
    }

    let index = 0;

    const show = (next) => {
        index = Math.max(0, Math.min(pages.length - 1, next));

        pages.forEach((page, i) => page.classList.toggle('hidden', i !== index));

        dots.forEach((dot, i) => {
            dot.classList.toggle('is-active', i === index);
            if (i === index) {
                dot.setAttribute('aria-current', 'true');
            } else {
                dot.removeAttribute('aria-current');
            }
        });

        if (prevBtn) {
            prevBtn.classList.toggle('hidden', index === 0);
        }
        if (nextBtn) {
            nextBtn.classList.toggle('hidden', index === pages.length - 1);
        }
        if (doneBtn) {
            doneBtn.classList.toggle('hidden', index !== pages.length - 1);
        }
    };

    prevBtn?.addEventListener('click', () => show(index - 1));
    nextBtn?.addEventListener('click', () => show(index + 1));

    dots.forEach((dot, i) => dot.addEventListener('click', () => show(i)));

    // Fresh start each time the modal is opened.
    modal.addEventListener('alice:modal-open', () => show(0));

    // Arrow-key paging while this modal is open (ignore form fields).
    document.addEventListener('keydown', (event) => {
        if (modal.classList.contains('hidden')) {
            return;
        }
        const tag = event.target?.tagName;
        if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') {
            return;
        }
        if (event.key === 'ArrowLeft') {
            show(index - 1);
        } else if (event.key === 'ArrowRight') {
            show(index + 1);
        }
    });

    show(0);
}
