const DEBOUNCE_MS = 350;

export function initAutoFilters(root = document) {
    root.querySelectorAll('form[data-auto-filter]').forEach((form) => {
        const submit = () => {
            if (form.dataset.autoFilterBusy === '1') {
                return;
            }
            form.dataset.autoFilterBusy = '1';
            form.submit();
        };

        form.querySelectorAll('select').forEach((select) => {
            select.addEventListener('change', submit);
        });

        let timer;
        form.querySelectorAll('input[type="search"], input[type="text"]').forEach((input) => {
            input.addEventListener('input', () => {
                window.clearTimeout(timer);
                timer = window.setTimeout(submit, DEBOUNCE_MS);
            });
        });

        form.querySelectorAll('[data-search-clear]').forEach((clear) => {
            clear.addEventListener('click', () => {
                const search = form.querySelector('input[type="search"]');
                if (search) {
                    search.value = '';
                }
                submit();
            });
        });
    });
}
