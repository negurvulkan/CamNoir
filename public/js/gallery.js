(function() {
    const modal = document.getElementById('photo-modal');
    const modalImg = document.getElementById('photo-modal-image');
    const closeBtn = document.getElementById('photo-modal-close');

    if (!modal || !modalImg || !closeBtn) return;

    const galleryImages = document.querySelectorAll('.gallery-grid .photo img');

    function openModal(src, altText) {
        modalImg.src = src;
        modalImg.alt = altText || 'Event-Foto';
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        modal.classList.add('hidden');
        document.body.style.overflow = '';
    }

    galleryImages.forEach((img) => {
        img.addEventListener('click', () => openModal(img.src, img.alt));
    });

    closeBtn.addEventListener('click', closeModal);

    modal.addEventListener('click', (event) => {
        if (event.target === modal) {
            closeModal();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !modal.classList.contains('hidden')) {
            closeModal();
        }
    });
})();
