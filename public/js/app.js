const startBtn = document.getElementById('start-camera');
const switchBtn = document.getElementById('switch-camera');
const takeBtn = document.getElementById('take-photo');
const preview = document.getElementById('camera-preview');
const captureCanvas = document.getElementById('camera-canvas');
const toast = document.getElementById('toast');
const uploadStatus = document.getElementById('upload-status');
const uploadStatusText = document.getElementById('upload-status-text');
const pageHeader = document.querySelector('.header');
const cameraView = document.getElementById('camera-view');
const editorView = document.getElementById('editor-view');
const editorCanvas = document.getElementById('editor-canvas');
const torchBtn = document.getElementById('toggle-torch');
const tabButtons = document.querySelectorAll('[data-tab-target]');
const tabPanels = document.querySelectorAll('[data-tab-panel]');
const transformPanel = document.getElementById('transform-panel');
const addTextBtn = document.getElementById('add-text-btn');
const editTextBtn = document.getElementById('edit-text-btn');
const stickerPalette = document.getElementById('sticker-palette');
const framePalette = document.getElementById('frame-palette');
const colorFilterSelect = document.getElementById('color-filter-select');
const overlayFilterPalette = document.getElementById('overlay-filter-palette');
const overlayCategoryTabs = document.querySelectorAll('[data-overlay-category-tab]');
const overlaySliders = document.querySelectorAll('[data-overlay-slider]');
const overlayScopeInputs = document.querySelectorAll('input[name="overlay-scope"]');
const overlayBlendSelect = document.getElementById('overlay-blend-select');
const overlayOpacityInput = document.getElementById('overlay-opacity');
const overlayOpacityValue = document.getElementById('overlay-opacity-value');
const overlayScaleRange = document.getElementById('overlay-scale-range');
const overlayRotationRange = document.getElementById('overlay-rotation-range');
const overlayScaleValue = document.getElementById('overlay-scale-value');
const overlayRotationValue = document.getElementById('overlay-rotation-value');
const fontSelect = document.getElementById('font-select');
const editCancelBtn = document.getElementById('edit-cancel-btn');
const editConfirmBtn = document.getElementById('edit-confirm-btn');
const scaleUpBtn = document.getElementById('overlay-scale-up');
const scaleDownBtn = document.getElementById('overlay-scale-down');
const rotateLeftBtn = document.getElementById('overlay-rotate-left');
const rotateRightBtn = document.getElementById('overlay-rotate-right');
const openCodeModalBtn = document.getElementById('open-code-modal');
const closeCodeModalBtn = document.getElementById('close-code-modal');
const codeModal = document.getElementById('code-modal');
const bonusCodeForm = document.getElementById('bonus-code-form');
const bonusCodeInput = document.getElementById('bonus-code-input');
const codeFeedback = document.getElementById('code-feedback');
const bonusInfo = document.getElementById('bonus-info');
const totalLimitEl = document.getElementById('total-limit');
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
const overlayCategories = (window.CAM_CONFIG?.overlayCategories?.length
    ? window.CAM_CONFIG.overlayCategories
    : [{ id: 'alle-overlays', name: 'Alle Overlays', overlays: window.CAM_CONFIG?.overlayFilters || [] }]);

const overlayFilters = overlayCategories.reduce(
    (list, category) => {
        (category.overlays || []).forEach((overlay) => {
            list.push({ ...overlay, category: category.id });
        });
        return list;
    },
    [{ id: 'none', name: 'Kein Overlay', src: null, category: overlayCategories[0]?.id || 'alle-overlays' }]
);

let stream = null;
let videoDevices = [];
let currentDeviceIndex = 0;
let preferredFacingMode = 'environment';
let remaining = window.CAM_CONFIG?.remaining ?? 0;
let baseLimit = window.CAM_CONFIG?.baseLimit ?? 0;
let extraPhotos = window.CAM_CONFIG?.extraPhotos ?? 0;
let uploadQueue = JSON.parse(localStorage.getItem('noir_upload_queue') || '[]');
let overlays = [];
let selectedOverlay = null;
let draggingOverlay = null;
let dragStart = { x: 0, y: 0 };
let backgroundImage = null;
let torchEnabled = false;
let torchSupported = false;
const editorCtx = editorCanvas ? editorCanvas.getContext('2d') : null;
const stickerCache = new Map();
const frameCache = new Map();
const overlayFilterCache = new Map();
let frameOverlay = null;
let selectedColorFilterId = colorFilters[0]?.id || 'none';
let selectedOverlayFilterId = overlayFilters[0]?.id || 'none';
let overlayFilterScope = 'photo';
let overlayFilterImage = null;
let overlayFilterBlendMode = overlayBlendSelect?.value || 'screen';
let overlayFilterOpacity = overlayOpacityInput ? Number(overlayOpacityInput.value) / 100 : 0.8;

function updateTakeButtonAvailability() {
    if (!takeBtn) return;
    takeBtn.disabled = remaining <= 0;
}

function updateLimitsDisplay() {
    if (totalLimitEl) {
        totalLimitEl.textContent = baseLimit + extraPhotos;
    }
    if (bonusInfo) {
        bonusInfo.textContent = extraPhotos > 0 ? ` (inkl. ${extraPhotos} Bonus)` : '';
    }
}

function showToast(message) {
    toast.textContent = message;
    toast.classList.remove('hidden');
    setTimeout(() => toast.classList.add('hidden'), 2500);
}

function updateRemaining(count) {
    remaining = Math.max(0, count);
    const el = document.getElementById('remaining-count');
    if (el) el.textContent = remaining;
    updateLimitsDisplay();
    updateTakeButtonAvailability();
    if (remaining <= 0) showToast('Limit erreicht.');
}

function toggleCodeModal(show) {
    if (!codeModal) return;
    codeModal.classList.toggle('hidden', !show);
    if (show && bonusCodeInput) {
        bonusCodeInput.focus();
    }
    if (!show && codeFeedback) {
        codeFeedback.classList.add('hidden');
    }
}

function setCodeFeedback(message, success = false) {
    if (!codeFeedback) return;
    codeFeedback.textContent = message;
    codeFeedback.classList.remove('hidden', 'success', 'warning');
    codeFeedback.classList.add('alert', success ? 'success' : 'warning');
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

function getActiveVideoTrack() {
    return stream?.getVideoTracks?.()?.[0];
}

function updateTorchButton() {
    if (!torchBtn) return;
    const hideTorch = !torchSupported || !stream;
    torchBtn.classList.toggle('hidden', hideTorch);
    torchBtn.disabled = hideTorch;
    torchBtn.classList.toggle('active', torchEnabled);
    torchBtn.setAttribute('aria-pressed', torchEnabled ? 'true' : 'false');
    torchBtn.textContent = torchEnabled ? 'Blitz: An' : 'Blitz: Aus';
}

async function updateTorchSupport() {
    const track = getActiveVideoTrack();
    const capabilities = track?.getCapabilities?.();
    torchSupported = Boolean(capabilities?.torch);
    if (!torchSupported) {
        torchEnabled = false;
    }
    updateTorchButton();
}

async function setTorch(enabled) {
    const track = getActiveVideoTrack();
    const capabilities = track?.getCapabilities?.();
    if (!track || !capabilities?.torch) {
        torchSupported = false;
        torchEnabled = false;
        updateTorchButton();
        return;
    }
    try {
        await track.applyConstraints({ advanced: [{ torch: enabled }] });
        torchEnabled = enabled;
    } catch (err) {
        console.warn('Torch toggle failed', err);
        torchEnabled = false;
        showToast('Blitz nicht verfügbar.');
    }
    updateTorchButton();
}

async function disableTorch() {
    if (!torchEnabled) {
        torchEnabled = false;
        updateTorchButton();
        return;
    }
    await setTorch(false);
}

async function toggleTorch() {
    if (!torchSupported || !stream) return;
    await setTorch(!torchEnabled);
}

async function startCamera() {
    await loadVideoDevices();
    await startCameraWithConstraints();
}

async function startCameraWithConstraints(deviceId = null) {
    await stopCurrentStream();
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
        torchEnabled = false;
        await updateTorchSupport();
    } catch (e) {
        showToast('Kamera konnte nicht gestartet werden.');
        torchSupported = false;
        torchEnabled = false;
        updateTorchButton();
    }
}

async function stopCurrentStream() {
    await disableTorch();
    if (!stream) return;
    stream.getTracks().forEach((track) => track.stop());
    stream = null;
    torchSupported = false;
    torchEnabled = false;
    updateTorchButton();
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

function setActiveTab(target) {
    if (!target) return;
    tabButtons.forEach((btn) => {
        const isActive = btn.getAttribute('data-tab-target') === target;
        btn.classList.toggle('active', isActive);
        btn.setAttribute('aria-selected', isActive);
    });
    tabPanels.forEach((panel) => {
        const isActive = panel.getAttribute('data-tab-panel') === target;
        panel.classList.toggle('hidden', !isActive);
    });
    if (transformPanel) {
        const showTransform = target === 'stickers' || target === 'text';
        transformPanel.classList.toggle('hidden', !showTransform);
    }
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
        if (pageHeader) pageHeader.classList.add('hidden');
    } else {
        cameraView.classList.remove('hidden');
        editorView.classList.add('hidden');
        if (pageHeader) pageHeader.classList.remove('hidden');
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
    updateTransformControls();
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

function getActiveOverlayCategoryId() {
    const activeSlider = Array.from(overlaySliders || []).find((slider) => !slider.classList.contains('hidden'));
    return activeSlider?.getAttribute('data-overlay-slider') || overlayCategories[0]?.id;
}

function setActiveOverlayCategory(categoryId) {
    const targetId = categoryId || overlayCategories[0]?.id;
    if (!targetId) return;
    Array.from(overlayCategoryTabs || []).forEach((tab) => {
        const isActive = tab.getAttribute('data-overlay-category-tab') === targetId;
        tab.classList.toggle('active', isActive);
        tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
    });
    Array.from(overlaySliders || []).forEach((slider) => {
        const isActive = slider.getAttribute('data-overlay-slider') === targetId;
        slider.classList.toggle('hidden', !isActive);
        slider.classList.toggle('active', isActive);
    });
    updateOverlayFilterButtons();
}

function updateOverlayFilterButtons() {
    if (!overlayFilterPalette) return;
    const activeCategoryId = getActiveOverlayCategoryId();
    overlayFilterPalette.querySelectorAll('[data-overlay-id]').forEach((btn) => {
        const isActive = btn.getAttribute('data-overlay-id') === selectedOverlayFilterId;
        const slider = btn.closest('[data-overlay-slider]');
        const matchesCategory = !slider || slider.getAttribute('data-overlay-slider') === activeCategoryId;
        btn.classList.toggle('active', isActive && matchesCategory);
    });
}

function setOverlayFilter(filterId) {
    selectedOverlayFilterId = filterId;
    overlayFilterImage = null;
    const filter = overlayFilters.find((item) => item.id === filterId);
    if (filter?.category) {
        setActiveOverlayCategory(filter.category);
    } else {
        updateOverlayFilterButtons();
    }
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
    editorCtx.globalCompositeOperation = overlayFilterBlendMode || 'screen';
    editorCtx.globalAlpha = overlayFilterOpacity ?? 0.8;
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
    updateTransformControls();
    renderEditor();
}

function updateTransformControls() {
    if (!overlayScaleRange || !overlayRotationRange || !overlayScaleValue || !overlayRotationValue) return;
    const hasSelection = Boolean(selectedOverlay);
    overlayScaleRange.disabled = !hasSelection;
    overlayRotationRange.disabled = !hasSelection;
    if (!hasSelection) {
        overlayScaleRange.value = 100;
        overlayRotationRange.value = 0;
        overlayScaleValue.textContent = '100';
        overlayRotationValue.textContent = '0';
        return;
    }
    const scalePercent = Math.round((selectedOverlay.scale || 1) * 100);
    const rotationDeg = Math.round((selectedOverlay.rotation || 0) * (180 / Math.PI));
    overlayScaleRange.value = scalePercent;
    overlayRotationRange.value = rotationDeg;
    overlayScaleValue.textContent = scalePercent.toString();
    overlayRotationValue.textContent = rotationDeg.toString();
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
    setActiveTab('filter');
    updateTransformControls();
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

function editSelectedText() {
    if (!selectedOverlay || selectedOverlay.type !== 'text') {
        showToast('Bitte zuerst einen Text auswählen.');
        return;
    }
    const newText = prompt('Text bearbeiten', selectedOverlay.text || '');
    if (!newText || !newText.trim()) return;
    selectedOverlay.text = newText.trim();
    selectedOverlay.width = measureTextWidth(selectedOverlay.text, selectedOverlay.fontSize, selectedOverlay.fontFamily);
    renderEditor();
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
    updateTransformControls();
    renderEditor();
}

function modifyRotation(delta) {
    if (!selectedOverlay) return;
    selectedOverlay.rotation = (selectedOverlay.rotation || 0) + delta;
    updateTransformControls();
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
        updateTakeButtonAvailability();
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
    updateTakeButtonAvailability();
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

tabButtons.forEach((btn) => {
    btn.addEventListener('click', () => {
        const target = btn.getAttribute('data-tab-target');
        setActiveTab(target);
    });
});

overlayCategoryTabs?.forEach((tab) => {
    tab.addEventListener('click', () => {
        const categoryId = tab.getAttribute('data-overlay-category-tab');
        setActiveOverlayCategory(categoryId);
    });
});

startBtn?.addEventListener('click', startCamera);
switchBtn?.addEventListener('click', switchCamera);
takeBtn?.addEventListener('click', takePhoto);
torchBtn?.addEventListener('click', toggleTorch);
addTextBtn?.addEventListener('click', addTextOverlay);
editTextBtn?.addEventListener('click', editSelectedText);
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
    const categoryId = btn.getAttribute('data-overlay-category');
    if (categoryId) setActiveOverlayCategory(categoryId);
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
overlayBlendSelect?.addEventListener('change', () => {
    overlayFilterBlendMode = overlayBlendSelect.value || 'screen';
    renderEditor();
});
overlayOpacityInput?.addEventListener('input', () => {
    const value = Number(overlayOpacityInput.value);
    if (!Number.isNaN(value)) {
        overlayFilterOpacity = Math.min(1, Math.max(0, value / 100));
        if (overlayOpacityValue) overlayOpacityValue.textContent = value.toString();
        renderEditor();
    }
});
overlayScaleRange?.addEventListener('input', () => {
    if (!selectedOverlay) return;
    const value = Number(overlayScaleRange.value);
    if (Number.isNaN(value)) return;
    selectedOverlay.scale = Math.min(4, Math.max(0.2, value / 100));
    updateTransformControls();
    renderEditor();
});
overlayRotationRange?.addEventListener('input', () => {
    if (!selectedOverlay) return;
    const value = Number(overlayRotationRange.value);
    if (Number.isNaN(value)) return;
    selectedOverlay.rotation = (value * Math.PI) / 180;
    updateTransformControls();
    renderEditor();
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
window.addEventListener('beforeunload', disableTorch);
window.addEventListener('pagehide', disableTorch);
openCodeModalBtn?.addEventListener('click', () => toggleCodeModal(true));
closeCodeModalBtn?.addEventListener('click', () => toggleCodeModal(false));
codeModal?.addEventListener('click', (e) => {
    if (e.target === codeModal) toggleCodeModal(false);
});

const redeemErrorMessages = {
    invalid_request: 'Bitte gib einen Code ein.',
    code_not_found: 'Code nicht gefunden.',
    code_wrong_event: 'Dieser Code gehört zu einem anderen Event.',
    code_expired: 'Code ist abgelaufen.',
    code_used: 'Code wurde bereits vollständig genutzt.',
    code_used_session: 'Du hast diesen Code in dieser Session schon genutzt.',
    invalid_session: 'Session ungültig. Bitte Seite neu laden.',
    server_error: 'Serverfehler. Bitte später erneut versuchen.',
    event_not_found: 'Event nicht gefunden.',
};

bonusCodeForm?.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!bonusCodeInput) return;
    const code = bonusCodeInput.value.trim();
    if (!code) {
        setCodeFeedback('Bitte gib einen Code ein.');
        return;
    }

    const formData = new FormData();
    formData.append('code', code);
    formData.append('session_token', window.CAM_CONFIG?.sessionToken || '');
    formData.append('event_slug', window.CAM_CONFIG?.eventSlug || '');

    try {
        const res = await fetch(window.CAM_CONFIG?.redeemUrl || '/redeem-code', { method: 'POST', body: formData });
        const json = await res.json();
        if (json.success) {
            const granted = Number(json.extra_photos || 0);
            extraPhotos += granted;
            updateRemaining(Number(json.remaining ?? remaining));
            updateLimitsDisplay();
            setCodeFeedback(`+${granted} Fotos freigeschaltet.`, true);
            showToast('Bonus aktiviert!');
            bonusCodeInput.value = '';
        } else {
            const msg = redeemErrorMessages[json.error] || 'Code konnte nicht eingelöst werden.';
            setCodeFeedback(msg);
        }
    } catch (err) {
        setCodeFeedback('Es gab ein Problem beim Einlösen. Bitte später erneut versuchen.');
    }
});

updateLimitsDisplay();

if (overlaySliders && overlaySliders.length) {
    setActiveOverlayCategory(getActiveOverlayCategoryId());
}
updateOverlayFilterButtons();
if (overlayOpacityValue && overlayOpacityInput?.value) {
    overlayOpacityValue.textContent = overlayOpacityInput.value;
}
if (colorFilterSelect?.value) {
    selectedColorFilterId = colorFilterSelect.value;
}
setActiveTab('filter');
setOverlayFilter(selectedOverlayFilterId);
updateRemaining(remaining);
processQueue();
loadCustomFonts();
updateTransformControls();
