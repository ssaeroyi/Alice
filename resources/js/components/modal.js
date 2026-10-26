export const openModal = (modal) => {
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    document.body.style.overflow = 'hidden';
    window.SFX?.play?.('open');
    modal.dispatchEvent(new CustomEvent('alice:modal-open'));
};

export const closeModal = (modal) => {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    if (!document.querySelectorAll('[data-modal]:not(.hidden)').length) {
        document.body.style.overflow = '';
    }
    window.SFX?.play?.('close');
    modal.dispatchEvent(new CustomEvent('alice:modal-close'));
};

export function initModals(root = document) {
    // Delegated, so modal-open triggers injected later (e.g. the classwork
    // panel re-rendered in place after join/leave) work without re-binding.
    root.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-modal-open]');

        if (!trigger) {
            return;
        }

        const modal = root.getElementById(trigger.getAttribute('data-modal-open'));

        if (modal) {
            // Many openers sit inside <a> cards (streak / keys / saved words);
            // opening the modal must not follow the link.
            event.preventDefault();
            openModal(modal);
        }
    });

    root.querySelectorAll('[data-modal]').forEach((modal) => {
        modal.querySelectorAll('[data-modal-close]').forEach((button) => {
            button.addEventListener('click', () => closeModal(modal));
        });

        modal.addEventListener('click', (event) => {
            if (event.target === modal) {
                closeModal(modal);
            }
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') {
            return;
        }

        root.querySelectorAll('[data-modal]:not(.hidden)').forEach((modal) => {
            closeModal(modal);
        });
    });
}
