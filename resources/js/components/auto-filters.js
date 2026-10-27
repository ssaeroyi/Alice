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
                // Clearing returns to the search's home page instead of
                // submitting — a cleared field should never land the student
                // on a results page they didn't ask for.
                const home = form.dataset.searchClearUrl;
                if (home) {
                    window.location.assign(home);
                } else {
                    submit();
                }
            });
        });

        // Custom X button: visible only while the field has text. The browser's
        // native search X just clears; ours navigates home, so it must not
        // double up with the native one on the same field.
        const search = form.querySelector('input[type="search"]');
        const clears = form.querySelectorAll('[data-search-clear]');
        if (search && clears.length) {
            const paint = () => {
                clears.forEach((clear) => {
                    clear.style.display = search.value === '' ? 'none' : 'inline-flex';
                });
            };
            search.addEventListener('input', paint);
            paint();
        }
    });
}
