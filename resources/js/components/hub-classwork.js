/**
 * Student Hub "Classwork" panel.
 *
 * A single panel that groups a student's classes and assignments. The landing
 * view is a school-wide feed plus a grid of class cards; each card drills into
 * a per-class view (assignments, past work, classmates). The join/leave
 * actions post via fetch so the hub never does a full reload — the panel is
 * re-rendered in place and the tab badge updates.
 *
 * The join form lives in a modal (auto-opened via data-modal-open/modals.js)
 * and is submitted by this module. Leaving a class is confirmed through the
 * shared <x-modal id="hub-leave-class"> instead of a native confirm; the
 * confirm button then runs the same async leave as before. Errors stay inside
 * the join modal / surface as toasts.
 */
import { openModal, closeModal } from './modal.js';
import { showToast } from './toast.js';

let rootEl = null;
let cwRoot = null;
let leaveId = null;
let activeDrawer = null;
let escBound = false;

export function initHubClasswork(root = document) {
    rootEl = root;
    cwRoot = root.querySelector('[data-cw-root]');
    if (!cwRoot) return;

    wireLeaveConfirm();
    apply();

    if (!escBound) {
        escBound = true;
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && activeDrawer) closePeople();
        });
    }
}

function apply() {
    const joinForm = rootEl.querySelector('#hub-join-class-form');
    if (joinForm) joinForm.addEventListener('submit', onJoin);

    // A join/leave re-render can drop the open drawer node; never leave the
    // body scroll locked on a detached element.
    if (activeDrawer && !document.body.contains(activeDrawer)) {
        activeDrawer = null;
        document.body.style.overflow = '';
    }

    wireViews();
    wireLeaves();
    wireTabs();
    wirePeople();

    // Default to the classes landing view.
    selectView('classes');

    openFromUrl();
}

/**
 * A back-link from an assignment page can deep-link into a class drill-down
 * (?class=123&tab=past). Open that view once, then strip the params so a
 * refresh doesn't retrigger it.
 */
function openFromUrl() {
    const params = new URLSearchParams(window.location.search);
    const classId = params.get('class');
    if (!classId) return;

    const view = 'class-' + classId;
    if (!cwRoot.querySelector('[data-cw-view="' + view + '"]')) return;

    selectView(view);
    const tab = params.get('tab');
    if (tab && cwRoot.querySelector('[data-cw-tab="' + view + '-' + tab + '"]')) {
        selectTab(view + '-' + tab);
    }

    params.delete('class');
    params.delete('tab');
    const qs = params.toString();
    window.history.replaceState(null, '', window.location.pathname + (qs ? '?' + qs : ''));
}

function wireViews() {
    cwRoot.querySelectorAll('[data-cw-open]').forEach((btn) => {
        btn.addEventListener('click', () => selectView(btn.dataset.cwOpen));
    });
    cwRoot.querySelectorAll('[data-cw-back]').forEach((btn) => {
        btn.addEventListener('click', () => selectView('classes'));
    });
}

function selectView(name) {
    cwRoot.querySelectorAll('[data-cw-view]').forEach((block) => {
        const show = block.dataset.cwView === name;
        block.hidden = !show;
        if (show) block.removeAttribute('hidden');
    });
}

function wireTabs() {
    cwRoot.querySelectorAll('[data-cw-tab]').forEach((btn) => {
        btn.addEventListener('click', () => selectTab(btn.dataset.cwTab));
    });
}

function selectTab(name) {
    cwRoot.querySelectorAll('[data-cw-tab]').forEach((tab) => {
        const on = tab.dataset.cwTab === name;
        tab.setAttribute('aria-selected', on ? 'true' : 'false');
        tab.classList.toggle('bg-amber-400', on);
        tab.classList.toggle('bg-white', !on);
    });
    cwRoot.querySelectorAll('[data-cw-panel]').forEach((panel) => {
        const show = panel.dataset.cwPanel === name;
        panel.hidden = !show;
        if (show) panel.removeAttribute('hidden');
    });
}

function wirePeople() {
    cwRoot.querySelectorAll('[data-cw-people-open]').forEach((btn) => {
        btn.addEventListener('click', () => openPeople(btn.dataset.cwPeopleOpen));
    });
}

function openPeople(id) {
    const drawer = document.getElementById('cw-people-' + id);
    if (!drawer) return;

    drawer.hidden = false;
    drawer.removeAttribute('hidden');
    document.body.style.overflow = 'hidden';
    activeDrawer = drawer;
    drawer.querySelector('[data-cw-people-close]')?.focus();

    if (!drawer.dataset.wired) {
        drawer.dataset.wired = '1';
        drawer.querySelector('[data-cw-people-backdrop]')?.addEventListener('click', closePeople);
        drawer.querySelector('[data-cw-people-close]')?.addEventListener('click', closePeople);
    }
}

function closePeople() {
    if (!activeDrawer) return;
    activeDrawer.hidden = true;
    document.body.style.overflow = '';
    activeDrawer = null;
}

function wireLeaves() {
    cwRoot.querySelectorAll('[data-cw-leave-open]').forEach((btn) => {
        btn.addEventListener('click', () => {
            leaveId = btn.dataset.cwLeaveOpen;
            const nameEl = rootEl.querySelector('[data-cw-leave-name]');
            if (nameEl) nameEl.textContent = btn.dataset.className || '';
            openModal(rootEl.querySelector('#hub-leave-class'));
        });
    });
}

function wireLeaveConfirm() {
    const confirmBtn = rootEl.querySelector('[data-cw-leave-confirm]');
    if (!confirmBtn) return;
    confirmBtn.addEventListener('click', () => {
        closeModal(rootEl.querySelector('#hub-leave-class'));
        if (leaveId) onLeave(leaveId);
    });
}

async function onJoin(event) {
    event.preventDefault();
    const form = event.currentTarget;
    const code = form.elements.join_code?.value?.trim() || '';
    const errorBox = form.querySelector('[data-cw-join-error]');
    const submit = form.querySelector('button[type="submit"]');
    if (submit) {
        submit.disabled = true;
        submit.setAttribute('aria-busy', 'true');
    }

    try {
        const res = await fetch(cwRoot.dataset.joinUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
            },
            body: JSON.stringify({ join_code: code }),
        });

        const payload = await res.json().catch(() => ({}));

        if (!res.ok || !payload.ok) {
            if (errorBox) errorBox.textContent = payload?.errors?.join_code || 'We couldn\'t join that class. Check the code and try again.';
            return;
        }

        if (errorBox) errorBox.textContent = '';
        closeModal(document.getElementById('hub-join-class'));
        replacePanel(payload);
        if (payload.toast?.message) showToast(payload.toast.message, { tone: 'stamp' });
    } catch (err) {
        if (errorBox) errorBox.textContent = 'Something went wrong. Please try again.';
    } finally {
        if (submit) {
            submit.disabled = false;
            submit.removeAttribute('aria-busy');
        }
    }
}

async function onLeave(groupId) {
    const url = cwRoot.dataset.leaveUrl.replace('__ID__', groupId);
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    cwRoot.classList.add('alice-busy');
    cwRoot.setAttribute('aria-busy', 'true');

    try {
        const res = await fetch(url, {
            method: 'DELETE',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
        });

        const payload = await res.json().catch(() => ({}));

        if (!res.ok || !payload.ok) {
            showToast('Couldn\'t leave that class right now. Try again.', { tone: 'freeze' });
            return;
        }

        replacePanel(payload);
        if (payload.toast?.message) showToast(payload.toast.message, { tone: 'stamp' });
    } catch (err) {
        showToast('Couldn\'t leave that class right now. Try again.', { tone: 'freeze' });
    } finally {
        // replacePanel swaps in a fresh root without the busy class; finally
        // also covers the early-return "no html" path so busy can't linger.
        if (cwRoot) {
            cwRoot.classList.remove('alice-busy');
            cwRoot.removeAttribute('aria-busy');
        }
    }
}

function replacePanel(payload) {
    if (!payload.html || !cwRoot) return;

    const wrapper = cwRoot;
    const fresh = document.createElement('div');
    fresh.innerHTML = payload.html.trim();
    const freshRoot = fresh.querySelector('[data-cw-root]');

    if (!freshRoot) return;

    wrapper.replaceWith(freshRoot);
    cwRoot = freshRoot;

    apply();
    updateBadge(payload.activeCount);
}

function updateBadge(count) {
    const tab = rootEl?.querySelector('[data-hub-tab="classwork"]');

    if (tab) {
        let badge = Array.from(tab.querySelectorAll('span')).find((s) => s.classList.contains('rounded-full'));

        if (typeof count === 'number' && count > 0) {
            if (!badge) {
                badge = document.createElement('span');
                badge.className = 'absolute -top-2 -right-2 min-w-[22px] h-[22px] px-1.5 rounded-full bg-amber-400 border-2 border-[#2D231E] text-[#2D231E] font-pixel text-[9px] font-bold flex items-center justify-center shadow-[2px_2px_0px_0px_rgba(45,35,30,0.9)]';
                tab.appendChild(badge);
            }
            badge.textContent = count;
        } else if (badge) {
            badge.remove();
        }
    }

    // The nav-chip badges (desktop + mobile) live outside the hub; refresh or
    // drop them too so a join/leave keeps every entry point honest.
    rootEl?.querySelectorAll('[data-nav-classwork-badge]').forEach((navBadge) => {
        if (typeof count === 'number' && count > 0) {
            navBadge.textContent = count > 9 ? (count > 99 ? '99+' : '9+') : count;
        } else {
            navBadge.remove();
        }
    });
}
