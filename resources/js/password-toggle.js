export function initPasswordToggle(root = document) {
    root.querySelectorAll('[data-password-toggle]').forEach((button) => {
        const selector = button.getAttribute('data-password-toggle');
        const input = root.querySelector(selector);

        if (!input) {
            return;
        }

        const eye = button.querySelector('[data-icon="eye"]');
        const eyeOff = button.querySelector('[data-icon="eye-off"]');

        button.addEventListener('click', () => {
            const wasPassword = input.type === 'password';

            input.type = wasPassword ? 'text' : 'password';

            if (eye) {
                eye.classList.toggle('hidden', wasPassword);
            }

            if (eyeOff) {
                eyeOff.classList.toggle('hidden', !wasPassword);
            }
        });
    });
}
