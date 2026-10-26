export function initDevTools() {
    const root = document.querySelector('[data-dev-tools]');

    if (!root) {
        return;
    }

    const toggle = root.querySelector('[data-dev-tools-toggle]');
    const panel = root.querySelector('[data-dev-tools-panel]');
    const readout = root.querySelector('[data-dev-tools-readout]');
    const message = root.querySelector('[data-dev-tools-message]');
    const resetModal = root.querySelector('[data-dev-reset-modal]');
    const stateUrl = root.dataset.stateUrl;
    const runUrl = root.dataset.runUrl;

    if (!toggle || !panel) {
        return;
    }

    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

    let open = false;
    let stateLoaded = false;
    let clearTimer = null;

    const flashMessage = (text, ok) => {
        if (clearTimer) {
            clearTimeout(clearTimer);
        }
        message.textContent = text || '';
        message.classList.toggle('text-rose-300', ok === false);
        message.classList.toggle('text-emerald-300', ok !== false);
        clearTimer = setTimeout(() => { message.textContent = ''; }, 5000);
    };

    const render = (state) => {
        if (!state) {
            return;
        }

        const minutes = Math.floor((Number(state.reading_seconds_today) || 0) / 60);

        readout.querySelectorAll('[data-k]').forEach((el) => {
            const key = el.dataset.k;
            if (key === 'streak_display') {
                el.textContent = `${String(state.current_streak ?? 0)}d/${String(state.longest_streak ?? 0)}d`;
            } else if (key === 'minutes_display') {
                el.textContent = `${minutes}m`;
            } else if (key === 'email') {
                el.textContent = state.email ?? '-';
                el.title = state.email ?? '';
            } else {
                const value = state[key];
                el.textContent = typeof value === 'number' ? Number(value).toLocaleString() : String(value ?? '-');
            }
        });
    };

    const fetchState = async () => {
        try {
            const res = await fetch(stateUrl, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!res.ok) {
                return;
            }
            const data = await res.json();
            if (data && data.ok) {
                render(data);
                stateLoaded = true;
            }
        } catch {
            // Preview may have just ended (session flushed) — stay quiet.
        }
    };

    toggle.addEventListener('click', () => {
        open = !open;
        panel.classList.toggle('hidden', !open);
        toggle.setAttribute('aria-expanded', String(open));
        toggle.classList.toggle('bg-amber-400', open);
        toggle.classList.toggle('text-[#2D231E]', open);
        toggle.classList.toggle('border-amber-300', open);
        if (open && !stateLoaded) {
            fetchState();
        }
    });

    const runAction = async (btn) => {
        const action = btn.dataset.devAction;

        btn.disabled = true;
        btn.classList.add('opacity-50', 'cursor-wait');

        const payload = { action };
        for (const attr of ['amount', 'minutes', 'kind', 'game', 'score']) {
            if (btn.dataset[attr] !== undefined) {
                payload[attr] = btn.dataset[attr];
            }
        }

        try {
            const res = await fetch(runUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(payload),
            });

            const data = await res.json().catch(() => null);

            if (data && data.ok) {
                flashMessage(data.message, true);
            } else {
                flashMessage(data?.message || 'Request failed', false);
            }

            if (data && data.state) {
                render(data.state);
            }
        } catch {
            flashMessage('Could not reach the Dev Tools endpoint.', false);
        } finally {
            btn.disabled = false;
            btn.classList.remove('opacity-50', 'cursor-wait');
        }
    };

    let resetBtn = null;

    if (resetModal) {
        const closeModal = () => resetModal.classList.add('hidden');
        resetBtn = root.querySelector('[data-dev-action="reset"]');

        resetBtn?.addEventListener('click', () => resetModal.classList.remove('hidden'));
        resetModal.querySelector('[data-dev-reset-cancel]')?.addEventListener('click', closeModal);
        resetModal.querySelector('[data-dev-reset-backdrop]')?.addEventListener('click', closeModal);
        resetModal.querySelector('[data-dev-reset-confirm]')?.addEventListener('click', () => {
            closeModal();
            if (resetBtn) {
                runAction(resetBtn);
            }
        });
    }

    root.querySelectorAll('[data-dev-action]').forEach((btn) => {
        if (btn.dataset.devAction === 'reset' && resetModal) {
            return; // Handled by the modal above.
        }

        btn.addEventListener('click', () => runAction(btn));
    });
}