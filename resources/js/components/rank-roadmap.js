import { openModal, closeModal } from './modal.js';

const fmt = (n) => new Intl.NumberFormat('en-US').format(n);

function describeRow(tiers, points, currentTier, isTop, next, toNext, index) {
    const tier = tiers[index];
    if (!tier) {
        return '';
    }
    if (index === currentTier) {
        return isTop
            ? `You are here — ${tier.name} at the top of the ladder!`
            : `You are here — ${tier.name} (${fmt(points)} pts). ${fmt(toNext)} to reach ${next.name}.`;
    }
    if (index < currentTier) {
        return `Unlocked — ${tier.name}. Reached at ${fmt(tier.min)} pts.`;
    }
    return `Locked — ${tier.name} unlocks at ${fmt(tier.min)} pts. ${fmt(Math.max(0, tier.min - points))} more to go.`;
}

export function initRankRoadmap(root = document) {
    const modal = root.getElementById('rank-roadmap-modal');
    if (!modal) {
        return;
    }

    const dataEl = modal.querySelector('[data-rank-map-data]');
    const detail = modal.querySelector('[data-rank-map-detail]');
    const rows = Array.from(modal.querySelectorAll('[data-rank-map-row]'));

    if (!dataEl || !detail) {
        return;
    }

    let data;
    try {
        data = JSON.parse(dataEl.textContent.trim());
    } catch {
        return;
    }

    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const currentRow = rows[data.currentTier] ?? null;

    const setFinal = () => {
        rows.forEach((row) => {
            row.style.transitionDelay = '0ms';
            row.classList.remove('rank-map-row-anim');
        });
    };

    const focusCurrent = () => {
        if (currentRow) {
            currentRow.focus({ preventScroll: true });
            currentRow.scrollIntoView({ block: 'nearest', inline: 'nearest' });
        }
    };

    const animateIn = () => {
        setFinal();
        if (!reduced) {
            rows.forEach((row) => {
                row.style.transitionDelay = '0ms';
                row.classList.add('rank-map-row-anim');
            });
            void modal.offsetWidth;

            rows.forEach((row, index) => {
                row.style.transitionDelay = `${80 + index * 55}ms`;
                row.classList.remove('rank-map-row-anim');
            });
        }
        focusCurrent();
    };

    const selectRow = (row) => {
        const index = Number(row.getAttribute('data-index'));
        detail.textContent = describeRow(data.tiers, data.points, data.currentTier, data.isTop, data.next, data.toNext, index);
        rows.forEach((other) => {
            if (other === row) {
                other.classList.add('ring-2', 'ring-[#2D231E]');
                other.setAttribute('aria-pressed', 'true');
            } else {
                other.classList.remove('ring-2', 'ring-[#2D231E]');
            }
        });
    };

    rows.forEach((row) => {
        const onActivate = (event) => {
            event.preventDefault();
            selectRow(row);
        };
        row.addEventListener('click', onActivate);
        row.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
                onActivate(event);
            }
        });
    });

    // Open triggers: close any enclosing modal first, then open the roadmap so
    // Escape closes only the roadmap (modal.js closes every open modal anyway).
    root.querySelectorAll('[data-rank-roadmap-open]').forEach((trigger) => {
        trigger.addEventListener('click', () => {
            const enclosing = trigger.closest('[data-modal]');
            if (enclosing && enclosing !== modal) {
                closeModal(enclosing);
            }
            openModal(modal);
        });
    });

    // openModal() dispatches alice:modal-open, which drives the wipe/stagger.
    modal.addEventListener('alice:modal-open', animateIn);

    // Restore the sensible default every time the modal closes.
    modal.addEventListener('alice:modal-close', () => {
        detail.textContent = data.isTop
            ? 'You reached the top of the ladder!'
            : `${fmt(data.toNext)} more points to reach ${data.next.name}`;
        rows.forEach((row) => row.classList.remove('ring-2', 'ring-[#2D231E]'));
    });
}