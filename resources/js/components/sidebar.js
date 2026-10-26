export function initSidebars() {
    const toggle = document.querySelector('[data-sidebar-toggle]');
    const sidebar = document.querySelector('[data-sidebar]');
    const backdrop = document.querySelector('[data-sidebar-backdrop]');

    if (!toggle || !sidebar || !backdrop) {
        return;
    }

    const open = () => {
        sidebar.classList.remove('-translate-x-full');
        backdrop.classList.remove('hidden');
    };

    const close = () => {
        sidebar.classList.add('-translate-x-full');
        backdrop.classList.add('hidden');
    };

    toggle.addEventListener('click', open);
    backdrop.addEventListener('click', close);
    sidebar.querySelectorAll('a').forEach((link) => {
        link.addEventListener('click', close);
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            close();
        }
    });
}