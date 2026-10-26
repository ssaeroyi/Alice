/**
 * Journal "HOW TO FINISH?" help modal (student/reader.blade.php).
 *
 * Two levels of navigation inside one x-modal:
 *   1. A left sidebar ([data-jh-tab]) switching between OVERVIEW, ACTIONS,
 *      MISSIONS and SKINS panels ([data-jh-panel]).
 *   2. The ACTIONS panel is long, so it paginates one action type per page
 *      ([data-jh-pager] -> [data-jh-page]) with the same dot + Previous/Next
 *      row the HOW ALICE WORKS modal uses (.help-pager-dot is shared CSS).
 *
 * Both levels reset to OVERVIEW / page 1 every time the modal opens
 * (alice:modal-open, fired by components/modal.js). Arrow keys page through
 * ACTIONS only while that panel is visible, so they never move a hidden pager.
 *
 * No-JS fallback: the markup ships with every section and action page
 * stacked, so the modal still reads (long and scrollable) without this script.
 */
export function initJournalHelp() {
    document.querySelectorAll('[data-journal-help]').forEach(setupJournalHelp);
}

function setupJournalHelp(root) {
    const modal = root.closest('[data-modal]');
    const tabs = Array.from(root.querySelectorAll('[data-jh-tab]'));
    const panels = Array.from(root.querySelectorAll('[data-jh-panel]'));
    const pages = Array.from(root.querySelectorAll('[data-jh-page]'));
    const dots = Array.from(root.querySelectorAll('[data-jh-dot]'));
    const prevBtn = root.querySelector('[data-jh-prev]');
    const nextBtn = root.querySelector('[data-jh-next]');

    if (!modal || tabs.length === 0 || panels.length === 0) {
        return;
    }

    let tab = tabs[0].dataset.jhTab;
    let page = 0;

    const paintTab = () => {
        tabs.forEach((btn) => {
            const on = btn.dataset.jhTab === tab;
            btn.classList.toggle('is-active', on);
            btn.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        panels.forEach((panel) => {
            panel.hidden = panel.dataset.jhPanel !== tab;
        });
    };

    const paintPage = () => {
        page = Math.max(0, Math.min(pages.length - 1, page));

        pages.forEach((el, i) => el.classList.toggle('hidden', i !== page));

        dots.forEach((dot, i) => {
            dot.classList.toggle('is-active', i === page);
            if (i === page) {
                dot.setAttribute('aria-current', 'true');
            } else {
                dot.removeAttribute('aria-current');
            }
        });

        if (prevBtn) {
            prevBtn.classList.toggle('hidden', page === 0);
        }
        if (nextBtn) {
            nextBtn.classList.toggle('hidden', page === pages.length - 1);
        }
    };

    tabs.forEach((btn) => {
        btn.addEventListener('click', () => {
            tab = btn.dataset.jhTab;
            paintTab();
        });
    });

    prevBtn?.addEventListener('click', () => {
        page -= 1;
        paintPage();
    });
    nextBtn?.addEventListener('click', () => {
        page += 1;
        paintPage();
    });
    dots.forEach((dot, i) => dot.addEventListener('click', () => {
        page = i;
        paintPage();
    }));

    // Fresh start each time the modal is opened.
    modal.addEventListener('alice:modal-open', () => {
        tab = tabs[0].dataset.jhTab;
        page = 0;
        paintTab();
        paintPage();
    });

    // Arrow-key paging, only while the ACTIONS panel is on screen.
    document.addEventListener('keydown', (event) => {
        if (modal.classList.contains('hidden') || tab !== 'actions') {
            return;
        }
        const tag = event.target?.tagName;
        if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') {
            return;
        }
        if (event.key === 'ArrowLeft') {
            page -= 1;
            paintPage();
        } else if (event.key === 'ArrowRight') {
            page += 1;
            paintPage();
        }
    });

    paintTab();
    paintPage();
}
