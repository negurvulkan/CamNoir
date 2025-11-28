const consent = document.getElementById('consent');
const startBtn = document.getElementById('start-camera');
const takeBtn = document.getElementById('take-photo');
const preview = document.getElementById('camera-preview');
const canvas = document.getElementById('camera-canvas');
const toast = document.getElementById('toast');
const uploadStatus = document.getElementById('upload-status');
const uploadStatusText = document.getElementById('upload-status-text');
let stream = null;
let remaining = window.CAM_CONFIG?.remaining ?? 0;
let uploadQueue = JSON.parse(localStorage.getItem('noir_upload_queue') || '[]');

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

function saveQueue() {
    localStorage.setItem('noir_upload_queue', JSON.stringify(uploadQueue));
}

async function processQueue() {
    if (!uploadQueue.length) return;
    const next = uploadQueue[0];
    try {
        const blob = await fetch(next.dataUrl).then((r) => r.blob());
        const formData = new FormData();
        formData.append('photo', blob, 'offline-photo.jpg');
        formData.append('session_token', next.sessionToken);
        const res = await fetch(window.CAM_CONFIG.uploadUrl, { method: 'POST', body: formData });
        const json = await res.json();
        if (json.success) {
            uploadQueue.shift();
            saveQueue();
            showToast('Offline-Foto synchronisiert.');
            updateRemaining(remaining - 1);
        }
    } catch (err) {
        console.warn('Queue upload failed', err);
    }
}
window.addEventListener('online', processQueue);

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
    preview.classList.add('flash');
    setTimeout(() => preview.classList.remove('flash'), 350);
    canvas.toBlob(async (blob) => {
        if (!blob) return;
        const formData = new FormData();
        formData.append('photo', blob, 'photo.jpg');
        formData.append('session_token', window.CAM_CONFIG.sessionToken);
        takeBtn.disabled = true;
        if (uploadStatus && uploadStatusText) {
            uploadStatusText.textContent = navigator.onLine ? 'Foto wird hochgeladen…' : 'Offline gespeichert…';
            uploadStatus.classList.remove('hidden');
        } else {
            showToast('Lade hoch ...');
        }
        try {
            if (!navigator.onLine) {
                const reader = new FileReader();
                reader.onload = () => {
                    uploadQueue.push({ dataUrl: reader.result, sessionToken: window.CAM_CONFIG.sessionToken, createdAt: Date.now() });
                    saveQueue();
                    showToast('Offline gespeichert – synchronisiert später.');
                };
                reader.readAsDataURL(blob);
            } else {
                const res = await fetch(window.CAM_CONFIG.uploadUrl, {
                    method: 'POST',
                    body: formData
                });
                const json = await res.json();
                if (json.success) {
                    updateRemaining(remaining - 1);
                    if (uploadStatus && uploadStatusText) {
                        uploadStatusText.textContent = 'Gespeichert! Löschcode: ' + json.delete_code;
                    }
                    showToast('Foto gespeichert. Löschcode: ' + json.delete_code);
                } else {
                    showToast(json.error || 'Upload fehlgeschlagen.');
                }
            }
        } catch (err) {
            showToast('Upload nicht möglich. In Queue gespeichert.');
            const reader = new FileReader();
            reader.onload = () => {
                uploadQueue.push({ dataUrl: reader.result, sessionToken: window.CAM_CONFIG.sessionToken, createdAt: Date.now() });
                saveQueue();
            };
            reader.readAsDataURL(blob);
        } finally {
            if (uploadStatus) {
                setTimeout(() => uploadStatus.classList.add('hidden'), 1200);
            }
            if (remaining > 0) takeBtn.disabled = !consent.checked;
            processQueue();
        }
    }, 'image/jpeg', 0.9);
}

consent?.addEventListener('change', () => {
    takeBtn.disabled = !consent.checked || remaining <= 0;
});

startBtn?.addEventListener('click', startCamera);
takeBtn?.addEventListener('click', takePhoto);
updateRemaining(remaining);
processQueue();
