document.addEventListener('click', (e) => {
    const thumbnail = e.target.closest('.viewer-thumbnail');
    if (!thumbnail) return;

    const modal = document.getElementById('image-viewer-modal');
    const modalImage = document.getElementById('viewer-image');
    if (!modal || !modalImage) return;

    e.preventDefault();
    modalImage.src = thumbnail.src;
    openModal('image-viewer-modal');
});