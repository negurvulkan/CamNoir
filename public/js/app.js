const consent = document.getElementById('consent');
const startBtn = document.getElementById('start-camera');
const switchBtn = document.getElementById('switch-camera');
const takeBtn = document.getElementById('take-photo');
const preview = document.getElementById('camera-preview');
const captureCanvas = document.getElementById('camera-canvas');
const toast = document.getElementById('toast');
const uploadStatus = document.getElementById('upload-status');
const uploadStatusText = document.getElementById('upload-status-text');
const cameraView = document.getElementById('camera-view');
const editorView = document.getElementById('editor-view');
const editorCanvas = document.getElementById('editor-canvas');
const addTextBtn = document.getElementById('add-text-btn');
const stickerPalette = document.getElementById('sticker-palette');
const framePalette = document.getElementById('frame-palette');
const colorFilterSelect = document.getElementById('color-filter-select');
const overlayFilterPalette = document.getElementById('overlay-filter-palette');
const overlayScopeInputs = document.querySelectorAll('input[name="overlay-scope"]');
const fontSelect = document.getElementById('font-select');
const editCancelBtn = document.getElementById('edit-cancel-btn');
const editConfirmBtn = document.getElementById('edit-confirm-btn');
const scaleUpBtn = document.getElementById('overlay-scale-up');
const scaleDownBtn = document.getElementById('overlay-scale-down');
const rotateLeftBtn = document.getElementById('overlay-rotate-left');
const rotateRightBtn = document.getElementById('overlay-rotate-right');
const availableFonts = window.CAM_CONFIG?.fonts || [{ name: 'Arial', url: null }];
const defaultColorFilters = [
    { id: 'none', name: 'Kein Filter', css: 'none' },
    { id: 'noir-classic', name: 'Noir Classic', css: 'grayscale(1) contrast(1.12) brightness(0.96)' },
    { id: 'noir-punch', name: 'Noir Punch', css: 'grayscale(0.85) contrast(1.24) brightness(0.94) saturate(0.9)' },
    { id: 'noir-soft', name: 'Noir Soft', css: 'grayscale(1) contrast(1.05) brightness(1.02) saturate(0.8)' },
    { id: 'noir-warm', name: 'Warm Noir', css: 'grayscale(0.8) sepia(0.12) contrast(1.1) brightness(0.98)' }
];
const colorFilters = (window.CAM_CONFIG?.colorFilters?.length ? window.CAM_CONFIG.colorFilters : defaultColorFilters).reduce(
    (list, filter) => {
        if (!list.find((item) => item.id === filter.id)) list.push(filter);
        return list;
    },
    [{ id: 'none', name: 'Kein Filter', css: 'none' }]
);
const overlayFilters = [
    { id: 'none', name: 'Kein Overlay', src: null },
    ...(window.CAM_CONFIG?.overlayFilters || [])
];

let stream = null;
let videoDevices = [];
let currentDeviceIndex = 0;
let preferredFacingMode = 'environment';
let remaining = window.CAM_CONFIG?.remaining ?? 0;
let uploadQueue = JSON.parse(localStorage.getItem('noir_upload_queue') || '[]');
let overlays = [];
let selectedOverlay = null;
let draggingOverlay = null;
let dragStart = { x: 0, y: 0 };
let backgroundImage = null;
const editorCtx = editorCanvas ? editorCanvas.getContext('2d') : null;
const stickerCache = new Map();
const frameCache = new Map();
const overlayFilterCache = new Map();
let frameOverlay = null;
let selectedColorFilterId = colorFilters[0]?.id || 'none';
let selectedOverlayFilterId = overlayFilters[0]?.id || 'none';
let overlayFilterScope = 'photo';
let overlayFilterImage = null;

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
        if (takeBtn) takeBtn.disabled = true;
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
    await loadVideoDevices();
    await startCameraWithConstraints();
}

async function startCameraWithConstraints(deviceId = null) {
    stopCurrentStream();
    const constraints = { video: {} };
    if (deviceId) {
        constraints.video.deviceId = { exact: deviceId };
    } else {
        constraints.video.facingMode = preferredFacingMode;
    }
    try {
        stream = await navigator.mediaDevices.getUserMedia(constraints);
        if (preview) preview.srcObject = stream;
        await loadVideoDevices();
        const activeDeviceId = stream?.getVideoTracks?.()?.[0]?.getSettings?.()?.deviceId;
        if (activeDeviceId) {
            const idx = videoDevices.findIndex((device) => device.deviceId === activeDeviceId);
            if (idx >= 0) currentDeviceIndex = idx;
        }
        updateSwitchAvailability();
    } catch (e) {
        showToast('Kamera konnte nicht gestartet werden.');
    }
}

function stopCurrentStream() {
    if (!stream) return;
    stream.getTracks().forEach((track) => track.stop());
    stream = null;
}

async function loadVideoDevices() {
    if (!navigator.mediaDevices?.enumerateDevices) return;
    const devices = await navigator.mediaDevices.enumerateDevices();
    videoDevices = devices.filter((device) => device.kind === 'videoinput');
    updateSwitchAvailability();
}

function updateSwitchAvailability() {
    if (!switchBtn) return;
    switchBtn.disabled = !stream && videoDevices.length <= 1;
}

async function switchCamera() {
    if (!videoDevices.length) {
        await loadVideoDevices();
    }
    if (videoDevices.length > 1) {
        currentDeviceIndex = (currentDeviceIndex + 1) % videoDevices.length;
        const nextDevice = videoDevices[currentDeviceIndex];
        await startCameraWithConstraints(nextDevice.deviceId);
        return;
    }
    preferredFacingMode = preferredFacingMode === 'environment' ? 'user' : 'environment';
    await startCameraWithConstraints();
}

function toggleViews(editMode) {
    if (!cameraView || !editorView) return;
    if (editMode) {
        cameraView.classList.add('hidden');
        editorView.classList.remove('hidden');
    } else {
        cameraView.classList.remove('hidden');
        editorView.classList.add('hidden');
    }
}

function resetEditorState() {
    overlays = [];
    selectedOverlay = null;
    draggingOverlay = null;
    dragStart = { x: 0, y: 0 };
    backgroundImage = null;
    frameOverlay = null;
    if (editorCtx && editorCanvas) {
        editorCtx.clearRect(0, 0, editorCanvas.width, editorCanvas.height);
    }
}

function getOverlayBounds(overlay) {
    const w = (overlay.width || 0) * (overlay.scale || 1);
    const h = (overlay.height || 0) * (overlay.scale || 1);
    return { x: overlay.x - w / 2, y: overlay.y - h / 2, w, h };
}

function overlayHitTest(x, y) {
    for (let i = overlays.length - 1; i >= 0; i -= 1) {
        const bounds = getOverlayBounds(overlays[i]);
        if (x >= bounds.x && x <= bounds.x + bounds.w && y >= bounds.y && y <= bounds.y + bounds.h) {
            return overlays[i];
        }
    }
    return null;
}

function getActiveColorFilter() {
    return colorFilters.find((filter) => filter.id === selectedColorFilterId) || colorFilters[0];
}

function updateOverlayFilterButtons() {
    if (!overlayFilterPalette) return;
    overlayFilterPalette.querySelectorAll('[data-overlay-id]').forEach((btn) => {
        const isActive = btn.getAttribute('data-overlay-id') === selectedOverlayFilterId;
        btn.classList.toggle('active', isActive);
    });
}

function setOverlayFilter(filterId) {
    selectedOverlayFilterId = filterId;
    overlayFilterImage = null;
    updateOverlayFilterButtons();
    const filter = overlayFilters.find((item) => item.id === filterId);
    if (!filter || !filter.src) {
        renderEditor();
        return;
    }
    if (overlayFilterCache.has(filter.src)) {
        overlayFilterImage = overlayFilterCache.get(filter.src);
        renderEditor();
        return;
    }
    const img = new Image();
    img.onload = () => {
        overlayFilterCache.set(filter.src, img);
        overlayFilterImage = img;
        renderEditor();
    };
    img.crossOrigin = 'anonymous';
    img.src = filter.src;
}

function drawFilterTexture(scope) {
    if (!editorCtx || !editorCanvas) return;
    if (overlayFilterScope !== scope) return;
    if (!overlayFilterImage) return;
    editorCtx.save();
    editorCtx.drawImage(overlayFilterImage, 0, 0, editorCanvas.width, editorCanvas.height);
    editorCtx.restore();
}

function setSelected(overlay) {
    overlays.forEach((o) => { o.selected = false; });
    if (overlay) overlay.selected = true;
    selectedOverlay = overlay;
    if (selectedOverlay?.type === 'text' && fontSelect) {
        fontSelect.value = selectedOverlay.fontFamily || getSelectedFont();
    }
    renderEditor();
}

function drawOverlay(overlay) {
    if (!editorCtx) return;
    editorCtx.save();
    editorCtx.translate(overlay.x, overlay.y);
    editorCtx.rotate(overlay.rotation || 0);
    editorCtx.scale(overlay.scale || 1, overlay.scale || 1);
    if (overlay.type === 'sticker' && overlay.image) {
        editorCtx.drawImage(overlay.image, -(overlay.width / 2), -(overlay.height / 2), overlay.width, overlay.height);
    } else if (overlay.type === 'text') {
        const fontSize = overlay.fontSize || 32;
        const fontFamily = overlay.fontFamily || getSelectedFont();
        editorCtx.font = `bold ${fontSize}px "${fontFamily}"`;
        editorCtx.textAlign = 'center';
        editorCtx.textBaseline = 'middle';
        editorCtx.fillStyle = 'white';
        editorCtx.strokeStyle = 'black';
        editorCtx.lineWidth = 3;
        editorCtx.strokeText(overlay.text, 0, 0);
        editorCtx.fillText(overlay.text, 0, 0);
    }
    editorCtx.restore();

    if (overlay.selected) {
        const bounds = getOverlayBounds(overlay);
        editorCtx.save();
        editorCtx.setLineDash([6, 4]);
        editorCtx.strokeStyle = '#c8a2ff';
        editorCtx.lineWidth = 2;
        editorCtx.strokeRect(bounds.x, bounds.y, bounds.w, bounds.h);
        editorCtx.restore();
    }
}

function drawFrame(frame) {
    if (!editorCtx || !editorCanvas || !frame.image) return;
    editorCtx.save();
    editorCtx.drawImage(frame.image, 0, 0, editorCanvas.width, editorCanvas.height);
    editorCtx.restore();
}

function renderEditor(showSelection = true) {
    if (!editorCtx || !editorCanvas || !backgroundImage) return;
    editorCtx.clearRect(0, 0, editorCanvas.width, editorCanvas.height);
    editorCtx.save();
    const activeColorFilter = getActiveColorFilter();
    if (activeColorFilter?.css && activeColorFilter.css !== 'none') {
        editorCtx.filter = activeColorFilter.css;
    }
    editorCtx.drawImage(backgroundImage, 0, 0, editorCanvas.width, editorCanvas.height);
    editorCtx.restore();
    drawFilterTexture('photo');
    overlays.forEach((overlay) => {
        if (!showSelection) overlay.selected = false;
        drawOverlay(overlay);
    });
    if (frameOverlay?.image) {
        drawFrame(frameOverlay);
    }
    drawFilterTexture('composition');
}

function enterEditMode(image) {
    if (!editorCanvas) return;
    backgroundImage = image;
    overlays = [];
    selectedOverlay = null;
    frameOverlay = null;
    const maxWidth = 1600;
    const scale = image.width > maxWidth ? maxWidth / image.width : 1;
    editorCanvas.width = Math.round(image.width * scale);
    editorCanvas.height = Math.round(image.height * scale);
    toggleViews(true);
    renderEditor();
}

function addStickerOverlay(src) {
    if (!editorCanvas) return;
    const addOverlay = (img) => {
        const baseWidth = editorCanvas.width * 0.28;
        const ratio = img.height / img.width;
        const overlay = {
            type: 'sticker',
            image: img,
            x: editorCanvas.width / 2,
            y: editorCanvas.height / 2,
            scale: 1,
            rotation: 0,
            width: baseWidth,
            height: baseWidth * ratio,
            selected: false
        };
        overlays.push(overlay);
        setSelected(overlay);
    };

    if (stickerCache.has(src)) {
        addOverlay(stickerCache.get(src));
        return;
    }
    const img = new Image();
    img.onload = () => {
        stickerCache.set(src, img);
        addOverlay(img);
    };
    img.crossOrigin = 'anonymous';
    img.src = src;
}

function setFrameOverlay(src) {
    if (!editorCanvas) return;

    const applyFrame = (img) => {
        frameOverlay = {
            type: 'frame',
            image: img,
            width: editorCanvas.width,
            height: editorCanvas.height
        };
        renderEditor();
    };

    if (frameCache.has(src)) {
        applyFrame(frameCache.get(src));
        return;
    }
    const img = new Image();
    img.onload = () => {
        frameCache.set(src, img);
        applyFrame(img);
    };
    img.crossOrigin = 'anonymous';
    img.src = src;
}

function measureTextWidth(text, fontSize, fontFamily = getSelectedFont()) {
    if (!editorCtx) return text.length * fontSize * 0.6;
    editorCtx.font = `bold ${fontSize}px "${fontFamily}"`;
    return editorCtx.measureText(text).width;
}

function addTextOverlay() {
    if (!editorCanvas) return;
    const userText = prompt('Text eingeben');
    if (!userText || !userText.trim()) return;
    const fontSize = Math.max(24, Math.round(editorCanvas.width * 0.05));
    const fontFamily = getSelectedFont();
    const width = measureTextWidth(userText, fontSize, fontFamily);
    const overlay = {
        type: 'text',
        text: userText,
        x: editorCanvas.width / 2,
        y: editorCanvas.height / 2,
        scale: 1,
        rotation: 0,
        width,
        height: fontSize,
        fontSize,
        fontFamily,
        selected: false
    };
    overlays.push(overlay);
    setSelected(overlay);
}

function onPointerDown(e) {
    if (!editorCanvas) return;
    const rect = editorCanvas.getBoundingClientRect();
    const scaleX = editorCanvas.width / rect.width;
    const scaleY = editorCanvas.height / rect.height;
    const x = (e.clientX - rect.left) * scaleX;
    const y = (e.clientY - rect.top) * scaleY;
    const hit = overlayHitTest(x, y);
    if (hit) {
        draggingOverlay = hit;
        dragStart = { x, y };
        setSelected(hit);
        e.preventDefault();
    } else {
        setSelected(null);
    }
}

function onPointerMove(e) {
    if (!draggingOverlay || !editorCanvas) return;
    const rect = editorCanvas.getBoundingClientRect();
    const scaleX = editorCanvas.width / rect.width;
    const scaleY = editorCanvas.height / rect.height;
    const x = (e.clientX - rect.left) * scaleX;
    const y = (e.clientY - rect.top) * scaleY;
    const dx = x - dragStart.x;
    const dy = y - dragStart.y;
    draggingOverlay.x += dx;
    draggingOverlay.y += dy;
    dragStart = { x, y };
    renderEditor();
}

function onPointerUp() {
    draggingOverlay = null;
}

function modifyScale(factor) {
    if (!selectedOverlay) return;
    selectedOverlay.scale = Math.min(4, Math.max(0.2, selectedOverlay.scale * factor));
    renderEditor();
}

function modifyRotation(delta) {
    if (!selectedOverlay) return;
    selectedOverlay.rotation = (selectedOverlay.rotation || 0) + delta;
    renderEditor();
}

function getSelectedFont() {
    return fontSelect?.value || availableFonts[0]?.name || 'Arial';
}

async function loadCustomFonts() {
    const loaders = availableFonts
        .filter((font) => font.url)
        .map((font) => {
            const face = new FontFace(font.name, `url(${font.url})`);
            return face
                .load()
                .then((loaded) => {
                    document.fonts.add(loaded);
                })
                .catch((err) => console.warn('Font konnte nicht geladen werden:', font.name, err));
        });
    await Promise.all(loaders);
    renderEditor();
}

function dataURLToBlob(dataURL) {
    const parts = dataURL.split(',');
    const mime = parts[0].match(/:(.*?);/)[1];
    const bstr = atob(parts[1]);
    let n = bstr.length;
    const u8arr = new Uint8Array(n);
    while (n--) {
        u8arr[n] = bstr.charCodeAt(n);
    }
    return new Blob([u8arr], { type: mime });
}

function enqueueBlob(blob, dataUrl) {
    const addToQueue = (url) => {
        uploadQueue.push({ dataUrl: url, sessionToken: window.CAM_CONFIG.sessionToken, createdAt: Date.now() });
        saveQueue();
    };
    if (dataUrl) {
        addToQueue(dataUrl);
        return;
    }
    const reader = new FileReader();
    reader.onload = () => addToQueue(reader.result);
    reader.readAsDataURL(blob);
}

async function uploadBlob(blob, dataUrl) {
    if (!blob) return;
    if (takeBtn) takeBtn.disabled = true;
    if (uploadStatus && uploadStatusText) {
        uploadStatusText.textContent = navigator.onLine ? 'Foto wird hochgeladen…' : 'Offline gespeichert…';
        uploadStatus.classList.remove('hidden');
    } else {
        showToast('Lade hoch ...');
    }
    try {
        if (!navigator.onLine) {
            enqueueBlob(blob, dataUrl);
            showToast('Offline gespeichert – synchronisiert später.');
        } else {
            const formData = new FormData();
            formData.append('photo', blob, 'photo.jpg');
            formData.append('session_token', window.CAM_CONFIG.sessionToken);
            const res = await fetch(window.CAM_CONFIG.uploadUrl, { method: 'POST', body: formData });
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
        console.warn('Upload failed, enqueueing', err);
        showToast('Upload nicht möglich. In Queue gespeichert.');
        enqueueBlob(blob, dataUrl);
    } finally {
        if (uploadStatus) {
            setTimeout(() => uploadStatus.classList.add('hidden'), 1200);
        }
        toggleViews(false);
        resetEditorState();
        if (remaining > 0 && takeBtn) takeBtn.disabled = !consent?.checked;
        processQueue();
    }
}

async function takePhoto() {
    if (!stream || !captureCanvas) {
        showToast('Bitte Kamera zuerst starten.');
        return;
    }
    const track = stream.getVideoTracks()[0];
    const settings = track.getSettings();
    const width = settings.width || 960;
    const height = settings.height || 1280;
    captureCanvas.width = width;
    captureCanvas.height = height;
    const ctx = captureCanvas.getContext('2d');
    ctx.drawImage(preview, 0, 0, width, height);
    preview.classList.add('flash');
    setTimeout(() => preview.classList.remove('flash'), 350);
    const dataUrl = captureCanvas.toDataURL('image/jpeg', 0.9);
    const img = new Image();
    img.onload = () => enterEditMode(img);
    img.src = dataUrl;
    if (takeBtn) takeBtn.disabled = true;
}

function cancelEditing() {
    toggleViews(false);
    resetEditorState();
    if (remaining > 0 && takeBtn) takeBtn.disabled = !consent?.checked;
}

function finalizeEdit() {
    if (!editorCanvas || !backgroundImage) {
        showToast('Kein Foto zum Hochladen.');
        return;
    }
    renderEditor(false);
    const dataUrl = editorCanvas.toDataURL('image/jpeg', 0.9);
    const blob = dataURLToBlob(dataUrl);
    uploadBlob(blob, dataUrl);
}

consent?.addEventListener('change', () => {
    if (takeBtn) takeBtn.disabled = !consent.checked || remaining <= 0;
});

startBtn?.addEventListener('click', startCamera);
switchBtn?.addEventListener('click', switchCamera);
takeBtn?.addEventListener('click', takePhoto);
addTextBtn?.addEventListener('click', addTextOverlay);
stickerPalette?.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-src]');
    if (!btn) return;
    const src = btn.getAttribute('data-src');
    addStickerOverlay(src);
});
colorFilterSelect?.addEventListener('change', () => {
    selectedColorFilterId = colorFilterSelect.value;
    renderEditor();
});
overlayFilterPalette?.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-overlay-id]');
    if (!btn) return;
    const id = btn.getAttribute('data-overlay-id');
    setOverlayFilter(id);
});
overlayScopeInputs?.forEach((input) => {
    if (input.checked) {
        overlayFilterScope = input.value;
    }
    input.addEventListener('change', () => {
        if (input.checked) {
            overlayFilterScope = input.value;
            renderEditor();
        }
    });
});
framePalette?.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-src], [data-clear-frame]');
    if (!btn) return;
    if (btn.hasAttribute('data-clear-frame')) {
        frameOverlay = null;
        renderEditor();
        return;
    }
    const src = btn.getAttribute('data-src');
    setFrameOverlay(src);
});
fontSelect?.addEventListener('change', () => {
    const fontFamily = getSelectedFont();
    if (selectedOverlay?.type === 'text') {
        selectedOverlay.fontFamily = fontFamily;
        selectedOverlay.width = measureTextWidth(selectedOverlay.text, selectedOverlay.fontSize, fontFamily);
        renderEditor();
    }
});
editorCanvas?.addEventListener('pointerdown', onPointerDown);
window.addEventListener('pointermove', onPointerMove);
window.addEventListener('pointerup', onPointerUp);
scaleUpBtn?.addEventListener('click', () => modifyScale(1.1));
scaleDownBtn?.addEventListener('click', () => modifyScale(0.9));
rotateLeftBtn?.addEventListener('click', () => modifyRotation(-0.1));
rotateRightBtn?.addEventListener('click', () => modifyRotation(0.1));
editCancelBtn?.addEventListener('click', cancelEditing);
editConfirmBtn?.addEventListener('click', finalizeEdit);

updateOverlayFilterButtons();
if (colorFilterSelect?.value) {
    selectedColorFilterId = colorFilterSelect.value;
}
setOverlayFilter(selectedOverlayFilterId);
updateRemaining(remaining);
processQueue();
loadCustomFonts();
