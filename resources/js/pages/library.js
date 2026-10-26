/**
 * Library page enhancements:
 * 1. Hero carousel arrow controls
 * 2. Filter drawer + grouped toggles + auto-search
 *    (shared with the collection page — see components/filter-drawer.js)
 *    Back-nav scroll restore is handled globally by components/scroll-restore.js.
 */
import { initFilterDrawer, initAutoSearch } from '../components/filter-drawer.js';
import { initAjaxPager } from '../components/ajax-pager.js';

export default function initLibraryPage() {
    initCarousel();
    initFilterDrawer();
    initAutoSearch();
    initAjaxPager('[data-search-results]');
}

function initCarousel() {
    const carousel = document.querySelector('[data-carousel]');

    if (!carousel) {
        return;
    }

    const track = carousel.querySelector('[data-carousel-track]');
    const prev = carousel.querySelector('[data-carousel-prev]');
    const next = carousel.querySelector('[data-carousel-next]');

    if (!track || !prev || !next) {
        return;
    }

    const step = () => {
        const card = track.querySelector('.snap-start');

        return card ? card.getBoundingClientRect().width + 16 : 320;
    };

    prev.addEventListener('click', () => {
        track.scrollBy({ left: -step(), behavior: 'smooth' });
    });

    next.addEventListener('click', () => {
        track.scrollBy({ left: step(), behavior: 'smooth' });
    });
}
