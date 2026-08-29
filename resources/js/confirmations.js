import Swal from 'sweetalert2';

const isDarkMode = () => document.documentElement.classList.contains('dark');

function fireConfirm(message, confirmButtonText) {
    return Swal.fire({
        title: message,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: confirmButtonText ?? 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        reverseButtons: true,
        focusCancel: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#6b7280',
        background: isDarkMode() ? '#0f172a' : '#ffffff',
        color: isDarkMode() ? '#e2e8f0' : '#1f2937',
    });
}

document.addEventListener('click', (e) => {
    const el = e.target.closest('[data-confirm]');
    if (!el) return;

    e.preventDefault();

    const message = el.dataset.confirm;
    const confirmButtonText = el.dataset.confirmButton;
    const form = el.closest('form');

    fireConfirm(message, confirmButtonText).then((result) => {
        if (!result.isConfirmed) return;

        if (form) {
            form.submit();
        } else if (el.tagName === 'A') {
            window.location.href = el.href;
        }
    });
});