/**
 * Hub page enhancement (FRONTEND_GUIDELINES § pages/*.js loader).
 *
 * The hub owns the stats panel, which needs Chart.js. Chart.js is heavy, so
 * it lives in a lazily-loaded shared chunk (charts.js) instead of the global
 * app.js bundle. The tabs, goal dialogs, goal CLAIM buttons and task claims
 * are cheap and eager; only the stats module is pulled in on demand.
 */
import { initHubTabs } from '../components/hub-tabs.js';
import { initHubClasswork } from '../components/hub-classwork.js';
import { showToast } from '../components/toast.js';
import { openModal } from '../components/modal.js';

export default function () {
    document.addEventListener('hub:tab', (event) => animateCounts(event.detail));
    initHubTabs();
    initHubClasswork();
    initGoalClaim();
    initTaskClaims();

    const statsRoot = document.querySelector('[data-hub-stats]');
    if (statsRoot) {
        import('../components/hub-stats.js').then((mod) => {
            mod.initHubStats();
            mod.initHubReadingGoal();
            mod.initHubStudyGoal();
        });
    }
}

function csrfHeaders(extra = {}) {
    return {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
        ...extra,
    };
}

function refreshTotals(payload) {
    if (Number.isFinite(Number(payload.total_keys))) {
        const keys = Number(payload.total_keys);
        document.querySelectorAll('[data-keys-total]').forEach((el) => {
            el.textContent = keys.toLocaleString();
        });
    }
    if (Number.isFinite(Number(payload.total_points))) {
        const points = Number(payload.total_points);
        document.querySelectorAll('[data-points-total]').forEach((el) => {
            el.textContent = points.toLocaleString();
        });
    }
}

function refreshClaimAll() {
    const allBtn = document.querySelector('[data-task-claim-all]');
    if (!allBtn) return;

    const remaining = Array.from(document.querySelectorAll('[data-can-claim]')).filter((el) => !el.hidden);
    if (!remaining.length) {
        allBtn.remove();
    }
}

function showClaimModal(keys, points) {
    const modal = document.getElementById('task-claim-modal');
    const keysEl = modal?.querySelector('[data-claim-modal-keys]');
    const pointsEl = modal?.querySelector('[data-claim-modal-points]');
    if (!modal || !keysEl || !pointsEl) {
        showToast(
            `You got this! +${keys} key${Number(keys) === 1 ? '' : 's'}, +${points} point${Number(points) === 1 ? '' : 's'}.`,
            { tone: 'stamp' },
        );
        return;
    }
    keysEl.textContent = `+${keys}`;
    pointsEl.textContent = `+${points} points`;
    openModal(modal);
}

function swapToClaimed(wrap) {
    wrap.hidden = true;
    const chip = wrap.parentElement?.querySelector('[data-task-claimed-chip]');
    if (chip) {
        chip.classList.remove('hidden');
        chip.classList.add('inline-flex');
    }

    // Calm the card the moment it's claimed: kill the claimable pulse and
    // settle the border back to ink (was waiting for a full page refresh).
    const card = wrap.closest('[data-task-card]');
    if (card) {
        card.classList.remove('task-card-pulse', 'border-emerald-500');
        card.classList.add('border-[#2D231E]');
    }
    refreshHubBadge();
}

/** Repaint both HUB claimable badges (desktop + mobile) from the DOM count. */
function refreshHubBadge() {
    const remaining = Array.from(document.querySelectorAll('[data-can-claim]')).filter((el) => !el.hidden).length;
    document.querySelectorAll('[data-hub-claimable-badge]').forEach((badge) => {
        badge.textContent = remaining > 9 ? '9+' : String(remaining);
        badge.classList.toggle('hidden', remaining <= 0);
    });
}

function initGoalClaim() {
    const root = document.querySelector('[data-goal-claim-url]');
    if (!root) return;

    const url = root.dataset.goalClaimUrl;

    root.querySelectorAll('[data-goal-claim]').forEach((btn) => {
        btn.addEventListener('click', async () => {
            if (btn.disabled) return;
            btn.disabled = true;
            btn.setAttribute('aria-busy', 'true');

            const kind = btn.dataset.goalClaim === 'study' ? 'Study' : 'Reading';

            try {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: csrfHeaders(),
                    body: JSON.stringify({ goal: btn.dataset.goalClaim }),
                });

                const payload = await res.json().catch(() => ({}));

                if (!res.ok) {
                    showToast(
                        res.status === 422 && payload?.message
                            ? 'Goal not met yet. Keep going!'
                            : 'Couldn\'t claim right now. Try again.',
                        { tone: 'freeze' },
                    );
                    btn.disabled = false;
                    btn.removeAttribute('aria-busy');
                    return;
                }

                if (payload.already) {
                    showToast('Already claimed today.', { tone: 'freeze' });
                    btn.remove();
                    return;
                }

                window.Celebrate?.('reward', btn);
                showToast(`${kind} goal claimed! +${payload.keys} keys.`, { tone: 'stamp' });
                refreshTotals(payload);
                btn.remove();
            } catch (err) {
                showToast('Couldn\'t claim right now. Try again.', { tone: 'freeze' });
                btn.disabled = false;
                btn.removeAttribute('aria-busy');
            }
        });
    });
}

function initTaskClaims() {
    document.querySelectorAll('[data-task-claim-form]').forEach((form) => {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();

            const btn = form.querySelector('button[type="submit"]');
            const wrap = form.closest('[data-task-actions]');
            if (btn) {
                btn.disabled = true;
                btn.setAttribute('aria-busy', 'true');
            }

            try {
                const res = await fetch(form.action, {
                    method: 'POST',
                    headers: csrfHeaders(),
                    body: JSON.stringify({
                        task: form.querySelector('input[name="task"]')?.value,
                        origin: 'hub',
                    }),
                });

                const payload = await res.json().catch(() => ({}));

                if (!res.ok || !payload.ok) {
                    showToast(payload?.message || 'Couldn\'t claim that task right now. Try again.', { tone: 'freeze' });
                    if (btn) {
                        btn.disabled = false;
                        btn.removeAttribute('aria-busy');
                    }
                    return;
                }

                window.Celebrate?.('reward', btn || form);
                showClaimModal(payload.keys, payload.points);
                refreshTotals(payload);
                if (wrap) swapToClaimed(wrap);
                refreshClaimAll();
            } catch (err) {
                showToast('Couldn\'t claim that task right now. Try again.', { tone: 'freeze' });
                if (btn) {
                    btn.disabled = false;
                    btn.removeAttribute('aria-busy');
                }
            }
        });
    });

    const allBtn = document.querySelector('[data-task-claim-all]');
    if (allBtn) {
        allBtn.addEventListener('click', async () => {
            if (allBtn.disabled) return;
            allBtn.disabled = true;
            allBtn.setAttribute('aria-busy', 'true');

            try {
                const res = await fetch(allBtn.dataset.claimUrl, {
                    method: 'POST',
                    headers: csrfHeaders(),
                    body: JSON.stringify({}),
                });

                const payload = await res.json().catch(() => ({}));

                if (!res.ok || !payload.ok) {
                    showToast(payload?.message || 'Couldn\'t claim your tasks right now. Try again.', { tone: 'freeze' });
                    allBtn.disabled = false;
                    allBtn.removeAttribute('aria-busy');
                    return;
                }

                window.Celebrate?.('reward', allBtn);
                showClaimModal(payload.keys, payload.points);
                refreshTotals(payload);

                document.querySelectorAll('[data-can-claim]').forEach((wrap) => {
                    if (!wrap.hidden) swapToClaimed(wrap);
                });

                allBtn.remove();
            } catch (err) {
                showToast('Couldn\'t claim your tasks right now. Try again.', { tone: 'freeze' });
                allBtn.disabled = false;
                allBtn.removeAttribute('aria-busy');
            }
        });
    }
}

function animateCounts(panel) {
    if (panel !== 'tasks') return;
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    const root = document.querySelector('[data-hub-panel="tasks"]');
    if (!root) return;

    root.querySelectorAll('[data-count-up]').forEach((el) => {
        const target = parseInt(el.dataset.countUp, 10);
        if (!Number.isFinite(target) || target <= 0) return;

        const duration = 400;
        const start = performance.now();

        const tick = (now) => {
            const p = Math.min(1, (now - start) / duration);
            el.textContent = String(Math.round(target * (1 - (1 - p) ** 2)));
            if (p < 1) requestAnimationFrame(tick);
            else el.textContent = String(target);
        };

        el.textContent = '0';
        requestAnimationFrame(tick);
    });
}
