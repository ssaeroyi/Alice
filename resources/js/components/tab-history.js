function initTabHistory() {
    document.querySelectorAll('[data-tab-history]').forEach((strip) => {
        strip.addEventListener('click', (event) => {
            if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                return;
            }

            const link = event.target.closest('a');

            if (!link) {
                return;
            }

            event.preventDefault();

            const current = window.location.href.split('#')[0];
            const next = link.href.split('#')[0];

            if (current === next) {
                return;
            }

            window.history.replaceState(null, '', link.href);
            window.location.reload();
        });
    });
}

export { initTabHistory };