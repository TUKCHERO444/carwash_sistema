const FOCUSABLE_SELECTOR = 'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

const ANIMATION_MS = 300;
let lastFocusedElement = null;

function getFocusables(modal) {
    return Array.from(modal.querySelectorAll(FOCUSABLE_SELECTOR)).filter(
        (el) => el.offsetParent !== null || el === document.activeElement,
    );
}

function openModal(id) {
    const modal = document.getElementById(id);
    if (!modal || modal.classList.contains('hidden') === false) return;

    lastFocusedElement = document.activeElement;
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';

    if (modal.hasAttribute('data-modal-animated')) {
        requestAnimationFrame(() => {
            modal.classList.add('opacity-100');
            const panel = modal.querySelector('[data-modal-panel]');
            panel?.classList.remove('scale-95');
            panel?.classList.add('scale-100');
        });
    }

    getFocusables(modal)[0]?.focus();
}

function closeModal(id) {
    const modal = document.getElementById(id);
    if (!modal || modal.classList.contains('hidden')) return;

    if (modal.hasAttribute('data-modal-animated')) {
        modal.classList.remove('opacity-100');
        const panel = modal.querySelector('[data-modal-panel]');
        panel?.classList.remove('scale-100');
        panel?.classList.add('scale-95');
        setTimeout(() => {
            modal.classList.add('hidden');
            document.body.style.overflow = '';
        }, ANIMATION_MS);
    } else {
        modal.classList.add('hidden');
        document.body.style.overflow = '';
    }

    lastFocusedElement?.focus();
}

function handleKeydown(e) {
    const openModalEl = document.querySelector('[data-modal]:not(.hidden)');
    if (!openModalEl) return;

    if (e.key === 'Escape') {
        e.preventDefault();
        closeModal(openModalEl.id);
        return;
    }

    if (e.key === 'Tab') {
        const focusables = getFocusables(openModalEl);
        if (!focusables.length) return;
        const first = focusables[0];
        const last = focusables[focusables.length - 1];

        if (e.shiftKey && document.activeElement === first) {
            e.preventDefault();
            last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
            e.preventDefault();
            first.focus();
        }
    }
}

document.addEventListener('click', (e) => {
    const opener = e.target.closest('[data-modal-open]');
    if (opener) {
        e.preventDefault();
        openModal(opener.dataset.modalOpen);
        return;
    }

    const closer = e.target.closest('[data-modal-close]');
    if (closer) {
        e.preventDefault();
        closeModal(closer.closest('[data-modal]').id);
        return;
    }

    const overlay = e.target.closest('[data-modal-overlay]');
    if (overlay) {
        e.preventDefault();
        closeModal(overlay.closest('[data-modal]').id);
    }
});

document.addEventListener('keydown', handleKeydown);

window.openModal = openModal;
window.closeModal = closeModal;