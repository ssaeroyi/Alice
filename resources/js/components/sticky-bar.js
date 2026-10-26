const STUCK_SHADOW = 'shadow-[0_8px_24px_rgba(45,35,30,0.12)]';

export function initStickyBars() {
    document.querySelectorAll('[data-sticky-sentinel]').forEach((sentinel) => {
        const wrapper = sentinel.nextElementSibling;
        const bar = wrapper instanceof HTMLElement
            ? (wrapper.querySelector('[data-sticky-bar]') ?? wrapper.firstElementChild)
            : null;

        if (!bar || !('IntersectionObserver' in window)) {
            return;
        }

        new IntersectionObserver(([entry]) => {
            bar.classList.toggle(STUCK_SHADOW, !entry.isIntersecting);
        }).observe(sentinel);
    });
}
