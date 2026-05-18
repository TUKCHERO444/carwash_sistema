document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('image-viewer-modal');
    const modalImage = document.getElementById('viewer-image');
    const closeBtn = document.getElementById('close-image-viewer');
    const overlay = document.getElementById('image-viewer-overlay');

    if (!modal || !modalImage) return;

    function openModal(src) {
        modalImage.src = src;
        modal.classList.remove('hidden');
        // Usar requestAnimationFrame para asegurar que la clase 'hidden' se haya removido antes de iniciar la transición
        requestAnimationFrame(() => {
            modal.classList.add('opacity-100');
            modal.querySelector('div').classList.remove('scale-95');
            modal.querySelector('div').classList.add('scale-100');
        });
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        modal.classList.remove('opacity-100');
        modal.querySelector('div').classList.remove('scale-100');
        modal.querySelector('div').classList.add('scale-95');
        
        // Esperar a que termine la transición antes de ocultar
        setTimeout(() => {
            modal.classList.add('hidden');
            modalImage.src = '';
            document.body.style.overflow = '';
        }, 300);
    }


    // Usar delegación de eventos para manejar imágenes cargadas dinámicamente (como en el buscador)
    document.addEventListener('click', (e) => {
        const thumbnail = e.target.closest('.viewer-thumbnail');
        if (thumbnail) {
            e.preventDefault();
            openModal(thumbnail.src);
        }
    });

    closeBtn?.addEventListener('click', closeModal);
    overlay?.addEventListener('click', closeModal);

    // Cerrar con Escape
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !modal.classList.contains('hidden')) {
            closeModal();
        }
    });
});
