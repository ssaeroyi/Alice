export function initNoSpaceInputs(root = document) {
    root.querySelectorAll('[data-no-spaces]').forEach((input) => {
        input.addEventListener('keydown', (event) => {
            if (event.key === ' ') {
                event.preventDefault();
            }
        });

        input.addEventListener('input', () => {
            if (input.value.includes(' ')) {
                input.value = input.value.replaceAll(' ', '');
            }
        });
    });
}