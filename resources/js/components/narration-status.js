const POLL_MS = 2500;

// Live states keep the 2.5s poll running; everything else backs off so a
// settled story never churns the status endpoint (or the rate-limit bucket).
const LIVE = new Set(['queued', 'generating', 'awaiting-deploy', 'stalled']);

const STATE_CLASSES = {
    narrated: 'bg-emerald-100 text-emerald-800',
    partial: 'bg-amber-100 text-amber-800',
    stale: 'bg-amber-100 text-amber-800',
    missing: 'bg-[#F3ECE0] text-[#2D231E]',
    queued: 'bg-[#F3ECE0] text-[#2D231E]',
    generating: 'bg-sky-100 text-sky-700',
    'awaiting-deploy': 'bg-sky-100 text-sky-700',
    stalled: 'bg-rose-100 text-rose-700',
};

const REQUESTABLE = new Set(['missing', 'stale', 'partial', 'stalled']);

function csrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.content : '';
}

function buildLabel(state) {
    if (state.state === 'partial') {
        return `${state.covered} of ${state.total} narrated`;
    }
    if (state.state === 'generating') {
        const progress = state.progress || {};
        const done = progress.done ?? 0;
        const total = progress.total ?? state.total;
        return total ? `${done} of ${total} pages` : 'Generating...';
    }
    const labels = {
        narrated: 'Narrated',
        stale: 'Audio stale',
        missing: 'No narration',
        queued: 'Queued',
        'awaiting-deploy': 'Awaiting deploy',
        stalled: 'Generator stalled',
    };
    return labels[state.state] ?? state.state;
}

export function initNarrationStatus() {
    document.querySelectorAll('[data-narration]').forEach((root) => {
        const pill = root.querySelector('[data-narration-pill]');
        const progressWrap = root.querySelector('[data-narration-progress-wrap]');
        const progressBar = root.querySelector('[data-narration-progress]');
        const progressText = root.querySelector('[data-narration-progress-text]');
        const button = root.querySelector('[data-narration-request]');
        const idle = root.querySelector('[data-narration-request-idle]');
        const errorEl = root.querySelector('[data-narration-error]');

        if (!pill || !button) {
            return;
        }

        const statusUrl = root.dataset.narrationUrl;
        const requestUrl = root.dataset.narrationRequestUrl;
        let intervalId = null;

        // Keep the 2.5s poll alive only while a run is in flux; settled
        // states (narrated / missing / stale / partial) stop the timer so a
        // page left open never churns the status endpoint or its limiter.
        const keepPolling = (state) => {
            if (!state || typeof state.state !== 'string') {
                return;
            }

            const live = LIVE.has(state.state);

            if (live && intervalId === null) {
                intervalId = window.setInterval(poll, POLL_MS);
            } else if (!live && intervalId !== null) {
                window.clearInterval(intervalId);
                intervalId = null;
            }
        };

        const render = (state) => {
            if (!state || typeof state.state !== 'string') {
                return;
            }

            const css = STATE_CLASSES[state.state] ?? 'bg-[#F3ECE0] text-[#2D231E]';
            pill.className = `inline-flex items-center px-2.5 py-1 rounded-md text-[11px] font-bold uppercase tracking-wider ${css}`;
            pill.textContent = buildLabel(state);

            // Broadcast so the story-form tab dots can react live (narration
            // is one of the signals that lights up the Publish tab).
            window.document.dispatchEvent(new CustomEvent('alice:narration-status', { detail: state }));

            if (progressWrap && progressBar && progressText) {
                const running = state.state === 'generating';
                progressWrap.classList.toggle('hidden', !running && state.state !== 'awaiting-deploy');
                progressBar.style.width = '0%';
                progressText.textContent = '';

                if (running) {
                    const progress = state.progress || {};
                    const done = progress.done ?? 0;
                    const total = progress.total ?? state.total;
                    if (total) {
                        progressBar.style.width = `${Math.round((done / total) * 100)}%`;
                        progressText.textContent = `${done} of ${total} pages synthesized`;
                    }
                }
            }

            if (errorEl) {
                errorEl.textContent = state.progress?.error ?? '';
                errorEl.classList.toggle('hidden', !errorEl.textContent);
            }

            const canRequest = REQUESTABLE.has(state.state);
            button.disabled = !canRequest;
            if (idle) {
                idle.textContent = canRequest ? 'Generate narration' : `Generation not available (${buildLabel(state)})`;
            }

            keepPolling(state);
        };

        const poll = async () => {
            // Skip while the tab is hidden — a queued narration will be picked
            // up by the visibilitychange listener the moment it becomes visible.
            if (document.hidden) {
                return;
            }

            try {
                const response = await fetch(statusUrl, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });

                if (!response.ok) {
                    throw new Error(`status ${response.status}`);
                }

                render(await response.json());
            } catch (error) {
                // Keep the server-rendered state intact on transient failures.
            }
        };

        button.addEventListener('click', async () => {
            if (button.disabled) {
                return;
            }
            button.disabled = true;

            try {
                const response = await fetch(requestUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ story: root.dataset.narrationSlug }),
                });

                const data = await response.json();

                if (!response.ok) {
                    if (errorEl && data.message) {
                        errorEl.textContent = data.message;
                        errorEl.classList.toggle('hidden', false);
                    }
                    if (data.status) {
                        render(data.status);
                    } else {
                        button.disabled = false;
                        idle.textContent = 'Generate narration';
                    }
                    return;
                }

                if (errorEl) {
                    errorEl.textContent = '';
                    errorEl.classList.add('hidden');
                }
                render(data.status ?? { state: 'queued' });
                poll();
            } catch (error) {
                if (errorEl) {
                    errorEl.textContent = 'Narration request failed. Try again.';
                    errorEl.classList.toggle('hidden', false);
                }
                button.disabled = false;
                idle.textContent = 'Generate narration';
            }
        });

        poll();

        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) {
                poll();
            }
        });
    });
}