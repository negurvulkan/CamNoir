(function() {
    let openModals = 0;
    const previousOverflow = document.body.style.overflow;

    function lockBody() {
        if (openModals === 0) {
            document.body.style.overflow = 'hidden';
        }
        openModals += 1;
    }

    function unlockBody() {
        openModals = Math.max(0, openModals - 1);
        if (openModals === 0) {
            document.body.style.overflow = previousOverflow;
        }
    }

    // Photo viewer modal
    const modal = document.getElementById('photo-modal');
    const modalImg = document.getElementById('photo-modal-image');
    const closeBtn = document.getElementById('photo-modal-close');

    const galleryImages = document.querySelectorAll('.gallery-grid .photo img');

    function openPhotoModal(src, altText) {
        if (!modal || !modalImg) return;
        modalImg.src = src;
        modalImg.alt = altText || 'Event-Foto';
        modal.classList.remove('hidden');
        lockBody();
    }

    function closePhotoModal() {
        if (!modal) return;
        modal.classList.add('hidden');
        unlockBody();
    }

    if (modal && modalImg && closeBtn) {
        galleryImages.forEach((img) => {
            img.addEventListener('click', () => openPhotoModal(img.src, img.alt));
        });

        closeBtn.addEventListener('click', closePhotoModal);

        modal.addEventListener('click', (event) => {
            if (event.target === modal) {
                closePhotoModal();
            }
        });
    }

    // Delete request modal
    const requestModal = document.getElementById('delete-request-modal');
    const requestForm = document.getElementById('delete-request-form');
    const requestStatus = document.getElementById('delete-request-status');
    const requestClose = document.getElementById('delete-request-close');
    const requestCancel = document.getElementById('delete-request-cancel');
    const requestCode = document.getElementById('delete-request-code');
    const deleteButtons = document.querySelectorAll('.delete-request');
    let activePhotoCard = null;

    function openRequestModal(button) {
        if (!requestModal || !requestForm || !requestCode) return;
        requestForm.reset();
        requestStatus.textContent = '';
        requestCode.value = button.dataset.deleteCode || '';
        activePhotoCard = button.closest('figure');
        requestModal.classList.remove('hidden');
        lockBody();
    }

    function closeRequestModal() {
        if (!requestModal) return;
        requestModal.classList.add('hidden');
        unlockBody();
    }

    if (requestModal && requestForm && requestClose && requestCancel) {
        deleteButtons.forEach((btn) => {
            btn.addEventListener('click', (event) => {
                event.preventDefault();
                openRequestModal(btn);
            });
        });

        requestClose.addEventListener('click', closeRequestModal);
        requestCancel.addEventListener('click', closeRequestModal);

        requestModal.addEventListener('click', (event) => {
            if (event.target === requestModal) {
                closeRequestModal();
            }
        });

        requestForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            const submitBtn = requestForm.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
            }
            requestStatus.textContent = 'Antrag wird gesendet...';
            try {
                const response = await fetch(requestForm.action, {
                    method: 'POST',
                    body: new FormData(requestForm),
                });
                const data = await response.json().catch(() => null);
                if (!response.ok || !data?.success) {
                    throw new Error('Request failed');
                }
                requestStatus.textContent = 'Antrag eingegangen. Das Foto wird ausgeblendet.';
                if (activePhotoCard) {
                    activePhotoCard.remove();
                    activePhotoCard = null;
                }
                setTimeout(closeRequestModal, 300);
            } catch (err) {
                requestStatus.textContent = 'Antrag konnte nicht gesendet werden. Bitte versuche es später erneut.';
            } finally {
                if (submitBtn) {
                    submitBtn.disabled = false;
                }
            }
        });
    }

    // Global escape handler
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            if (requestModal && !requestModal.classList.contains('hidden')) {
                closeRequestModal();
            }
            if (modal && !modal.classList.contains('hidden')) {
                closePhotoModal();
            }
        }
    });
})();
