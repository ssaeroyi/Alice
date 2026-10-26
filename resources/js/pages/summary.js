/**
 * Post-read summary: chevrons for the "You may like next" carousel row.
 *
 * The reader-rank tween lives inline in the page (server renders the final
 * state first), so this module only adds the scroll affordance — it never
 * owns any summary data.
 */

const CARD_GAP = 16;

export default function init() {
    const scroller = document.querySelector('[data-suggest-scroll]');
    if (!scroller) return;

    const controls = document.querySelector('[data-suggest-controls]');
    const prev = document.querySelector('[data-suggest-prev]');
    const next = document.querySelector('[data-suggest-next]');
    if (!controls || !prev || !next) return;

    const card = scroller.firstElementChild;
    const stepDistance = () =>
        card ? card.getBoundingClientRect().width + CARD_GAP : window.innerWidth * 0.8;

    const syncVisibility = () => {
        const scrollable = scroller.scrollWidth > scroller.clientWidth + 1;
        controls.classList.toggle('hidden', !scrollable);
    };

    prev.addEventListener('click', () => {
        scroller.scrollBy({ left: -stepDistance(), behavior: 'smooth' });
    });

    next.addEventListener('click', () => {
        scroller.scrollBy({ left: stepDistance(), behavior: 'smooth' });
    });

    window.addEventListener('resize', syncVisibility);
    syncVisibility();
}