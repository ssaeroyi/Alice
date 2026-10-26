/**
 * Study dashboard (§10.6): AJAX daily-goal modal + tappable donut segments.
 */
export default function study() {
    // --- Goal modal ---
    const slider = document.querySelector('[data-goal-slider]');
    const output = document.querySelector('[data-goal-output]');
    const saveButton = document.querySelector('[data-save-goal]');

    if (!slider || !output || !saveButton) {
        return;
    }

    const formatNumber = (value) => new Intl.NumberFormat('en-US').format(value);

    slider.addEventListener('input', () => {
        output.textContent = formatNumber(slider.value);
    });

    saveButton.addEventListener('click', async () => {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
        const card = document.querySelector('[data-caught-up]');
        const wasCaughtUp = card?.dataset.caughtUp === 'true';

        saveButton.disabled = true;
        saveButton.textContent = 'SAVING...';

        try {
            const response = await fetch('/study/goal', {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify({ goal: Number(slider.value) }),
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const data = await response.json();

            // Inline updates - no page refresh.
            const goalText = document.querySelector('[data-goal-text]');
            const progressBar = document.querySelector('[data-progress-bar]');
            const reviewed = Number(document.querySelector('[data-reviewed-count]')?.textContent ?? 0);
            const queueSize = document.querySelector('[data-queue-size]');

            if (goalText) goalText.textContent = data.goal;
            if (progressBar) progressBar.style.width = `${Math.min(100, Math.round((reviewed / data.goal) * 100))}%`;
            if (queueSize && !wasCaughtUp) queueSize.textContent = data.queue_size;

            const stillCaughtUp = data.queue_size === 0;

            if (wasCaughtUp !== stillCaughtUp) {
                // The caught-up/ready states are structurally different -
                // a reload here keeps one source of truth.
                window.location.reload();
                return;
            }

            document.querySelector('[data-modal-close]')?.click();
            saveButton.textContent = 'Save';
        } catch (error) {
            saveButton.textContent = 'TRY AGAIN';
        } finally {
            saveButton.disabled = false;
        }
    });

    // --- Donut segment tap → filter words list ---
    const donutWrap = document.querySelector('[data-donut-wrap]');
    if (donutWrap) {
        donutWrap.addEventListener('click', (e) => {
            const tier = e.target.closest('[data-tier]')?.dataset.tier;
            if (tier) {
                window.location.href = `/study/words?filter=${encodeURIComponent(tier)}`;
            }
        });
    }
}
