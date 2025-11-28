const consent = document.getElementById('consent');
const startBtn = document.getElementById('start-camera');
const takeBtn = document.getElementById('take-photo');
const preview = document.getElementById('camera-preview');
const canvas = document.getElementById('camera-canvas');
const toast = document.getElementById('toast');
let stream = null;
let remaining = window.CAM_CONFIG?.remaining ?? 0;

function showToast(message) {
    toast.textContent = message;
    toast.classList.remove('hidden');
    setTimeout(() => toast.classList.add('hidden'), 2500);
}

function updateRemaining(count) {
    remaining = count;
    const el = document.getElementById('remaining-count');
    if (el) el.textContent = remaining;
    if (remaining <= 0) {
        takeBtn.disabled = true;
        showToast('Limit erreicht.');
    }
}

async function startCamera() {
    try {
        stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
        preview.srcObject = stream;
    } catch (e) {
        showToast('Kamera konnte nicht gestartet werden.');
    }
}

async function takePhoto() {
    if (!stream) {
        showToast('Bitte Kamera zuerst starten.');
        return;
    }
    const track = stream.getVideoTracks()[0];
    const settings = track.getSettings();
    const width = settings.width || 960;
    const height = settings.height || 1280;
    canvas.width = width;
    canvas.height = height;
    const ctx = canvas.getContext('2d');
    ctx.drawImage(preview, 0, 0, width, height);
    canvas.toBlob(async (blob) => {
        if (!blob) return;
        const formData = new FormData();
        formData.append('photo', blob, 'photo.jpg');
        formData.append('session_token', window.CAM_CONFIG.sessionToken);
        takeBtn.disabled = true;
        showToast('Lade hoch ...');
        try {
            const res = await fetch(window.CAM_CONFIG.uploadUrl, {
                method: 'POST',
                body: formData
            });
            const json = await res.json();
            if (json.success) {
                updateRemaining(remaining - 1);
                showToast('Foto gespeichert. Löschcode: ' + json.delete_code);
            } else {
                showToast(json.error || 'Upload fehlgeschlagen.');
            }
        } catch (err) {
            showToast('Upload nicht möglich.');
        } finally {
            if (remaining > 0) takeBtn.disabled = !consent.checked;
        }
    }, 'image/jpeg', 0.9);
}

consent?.addEventListener('change', () => {
    takeBtn.disabled = !consent.checked || remaining <= 0;
});

startBtn?.addEventListener('click', startCamera);
takeBtn?.addEventListener('click', takePhoto);
updateRemaining(remaining);
