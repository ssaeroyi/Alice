export function initSubmitGuards(root = document) {
    root.querySelectorAll('form[data-submit-guard]').forEach((form) => {
        form.addEventListener('submit', () => {
            if (form.dataset.submitting === 'true') {
                return;
            }

            const button = form.querySelector('button[type="submit"]');

            if (!button || button.disabled) {
                return;
            }

            form.dataset.submitting = 'true';
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');

            const idle = button.querySelector('[data-submit-idle]');
            const active = button.querySelector('[data-submit-active]');

            if (idle) {
                idle.hidden = true;
            }

            if (active) {
                active.hidden = false;
                active.classList.remove('hidden');
            }
        });
    });
}