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

        // Free-text search no longer auto-submits: typing used to jump the
        // user to a results page mid-thought. Selects and the explicit
        // clear button still submit; search fields submit on Enter only.

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
