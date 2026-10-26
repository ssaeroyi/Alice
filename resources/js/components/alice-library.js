const IMPORTING = 'Importing the Alice Library... This takes a moment.';

export function initLibraryImport() {
    const form = document.querySelector('[data-library-import]');
    const overlay = document.querySelector('[data-library-overlay]');
    const stage = document.querySelector('[data-library-stage]');

    if (!form || !overlay || !stage) {
        return;
    }

    form.addEventListener('submit', () => {
        overlay.classList.remove('hidden');
        stage.textContent = IMPORTING;
    });
}