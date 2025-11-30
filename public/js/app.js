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
const uploadBtn = document.getElementById('upload-photo');
const uploadInput = document.getElementById('upload-input');
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
const brightnessRange = document.getElementById('brightness-range');
const contrastRange = document.getElementById('contrast-range');
const brightnessValue = document.getElementById('brightness-value');
const contrastValue = document.getElementById('contrast-value');
const adjustmentResetBtn = document.getElementById('adjustment-reset-btn');
const fontSelect = document.getElementById('font-select');
const editCancelBtn = document.getElementById('edit-cancel-btn');
const editConfirmBtn = document.getElementById('edit-confirm-btn');
const scaleUpBtn = document.getElementById('overlay-scale-up');
const scaleDownBtn = document.getElementById('overlay-scale-down');
const rotateLeftBtn = document.getElementById('overlay-rotate-left');
const rotateRightBtn = document.getElementById('overlay-rotate-right');
const deleteOverlayBtn = document.getElementById('delete-overlay-btn');
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
let activePointerId = null;
let interactionMode = null;
let resizeStartDistance = 0;
let resizeStartScale = 1;
let rotateStartAngle = 0;
let rotateStartRotation = 0;
let backgroundImage = null;
let torchEnabled = false;
let torchSupported = false;
let cameraUnavailable = false;
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
const adjustmentDefaults = { brightness: 0, contrast: 0 };
let imageAdjustments = { ...adjustmentDefaults };
const adjustedBaseCanvas = document.createElement('canvas');
const adjustedBaseCtx = adjustedBaseCanvas.getContext('2d');
let adjustedBackgroundImage = null;
const MAX_IMPORT_DIMENSION = 2000;
const HANDLE_SIZE = 32;
const HANDLE_HIT_EXPANSION = 12;
const MIN_OVERLAY_SCALE = 0.2;
const MAX_OVERLAY_SCALE = 4;

function clamp(value, min, max) {
    return Math.min(max, Math.max(min, value));
}

function updateTakeButtonAvailability() {
    const captureDisabled = remaining <= 0;
    const cameraReady = Boolean(stream) && !cameraUnavailable;
    const uploadPrimary = cameraUnavailable;

    if (takeBtn) {
        takeBtn.disabled = captureDisabled || !cameraReady;
        takeBtn.classList.toggle('primary', cameraReady);
        takeBtn.classList.toggle('secondary', !cameraReady);
    }

    if (uploadBtn) {
        uploadBtn.disabled = captureDisabled;
        uploadBtn.classList.toggle('primary', uploadPrimary);
        uploadBtn.classList.toggle('secondary', !uploadPrimary);
    }
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

function loadImageFromSource(src) {
    return new Promise((resolve, reject) => {
        const img = new Image();
        img.onload = () => resolve(img);
        img.onerror = () => reject(new Error('Bild konnte nicht geladen werden.'));
        img.src = src;
    });
}

function loadFileAsImage(file) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => {
            loadImageFromSource(reader.result)
                .then(resolve)
                .catch(reject);
        };
        reader.onerror = () => reject(new Error('Datei konnte nicht gelesen werden.'));
        reader.readAsDataURL(file);
    });
}

async function scaleImageForEditor(image, mimeType = 'image/jpeg') {
    const largestSide = Math.max(image?.width || 0, image?.height || 0);
    if (!largestSide) {
        throw new Error('Bild ist leer.');
    }
    if (largestSide <= MAX_IMPORT_DIMENSION) return image;

    const scale = MAX_IMPORT_DIMENSION / largestSide;
    const canvas = document.createElement('canvas');
    canvas.width = Math.round((image.width || 0) * scale);
    canvas.height = Math.round((image.height || 0) * scale);
    const ctx = canvas.getContext('2d');
    ctx.drawImage(image, 0, 0, canvas.width, canvas.height);
    const mime = mimeType === 'image/png' ? 'image/png' : 'image/jpeg';
    const dataUrl = canvas.toDataURL(mime, mime === 'image/png' ? 0.92 : 0.9);
    return loadImageFromSource(dataUrl);
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
    if (!navigator.mediaDevices?.getUserMedia) {
        showToast('Keine Kamera-Unterstützung. Bitte Foto hochladen.');
        cameraUnavailable = true;
        updateTakeButtonAvailability();
        return;
    }
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
        cameraUnavailable = false;
        updateTakeButtonAvailability();
    } catch (e) {
        showToast('Kamera konnte nicht gestartet werden.');
        torchSupported = false;
        torchEnabled = false;
        updateTorchButton();
        stream = null;
        cameraUnavailable = true;
        updateTakeButtonAvailability();
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
    updateTakeButtonAvailability();
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
        disableTorch();
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
    adjustedBackgroundImage = null;
    frameOverlay = null;
    if (editorCtx && editorCanvas) {
        editorCtx.clearRect(0, 0, editorCanvas.width, editorCanvas.height);
    }
    updateTransformControls();
}

function updateAdjustmentUI() {
    if (brightnessRange) brightnessRange.value = imageAdjustments.brightness;
    if (contrastRange) contrastRange.value = imageAdjustments.contrast;
    if (brightnessValue) brightnessValue.textContent = imageAdjustments.brightness.toString();
    if (contrastValue) contrastValue.textContent = imageAdjustments.contrast.toString();
}

function applyImageAdjustments(triggerRender = true) {
    if (!backgroundImage || !editorCanvas || !adjustedBaseCtx) return;
    if (adjustedBaseCanvas.width !== editorCanvas.width || adjustedBaseCanvas.height !== editorCanvas.height) {
        adjustedBaseCanvas.width = editorCanvas.width;
        adjustedBaseCanvas.height = editorCanvas.height;
    }
    adjustedBaseCtx.clearRect(0, 0, adjustedBaseCanvas.width, adjustedBaseCanvas.height);
    adjustedBaseCtx.drawImage(backgroundImage, 0, 0, editorCanvas.width, editorCanvas.height);
    const imageData = adjustedBaseCtx.getImageData(0, 0, adjustedBaseCanvas.width, adjustedBaseCanvas.height);
    const data = imageData.data;
    const brightnessOffset = (clamp(imageAdjustments.brightness, -100, 100) / 100) * 255;
    const contrast = clamp(imageAdjustments.contrast, -100, 100);
    const contrastFactor = (259 * (contrast + 255)) / (255 * (259 - contrast));

    for (let i = 0; i < data.length; i += 4) {
        data[i] = clamp(contrastFactor * (data[i] - 128) + 128 + brightnessOffset, 0, 255);
        data[i + 1] = clamp(contrastFactor * (data[i + 1] - 128) + 128 + brightnessOffset, 0, 255);
        data[i + 2] = clamp(contrastFactor * (data[i + 2] - 128) + 128 + brightnessOffset, 0, 255);
    }

    adjustedBaseCtx.putImageData(imageData, 0, 0);
    adjustedBackgroundImage = adjustedBaseCanvas;
    if (triggerRender) renderEditor();
}

function resetImageAdjustments(triggerRender = true) {
    imageAdjustments = { ...adjustmentDefaults };
    updateAdjustmentUI();
    applyImageAdjustments(triggerRender);
}

function getOverlayBounds(overlay) {
    const w = (overlay.width || 0) * (overlay.scale || 1);
    const h = (overlay.height || 0) * (overlay.scale || 1);
    return { x: overlay.x - w / 2, y: overlay.y - h / 2, w, h };
}

function getCanvasCoordinates(e) {
    if (!editorCanvas) return { x: 0, y: 0 };
    const rect = editorCanvas.getBoundingClientRect();
    const scaleX = editorCanvas.width / rect.width;
    const scaleY = editorCanvas.height / rect.height;
    return {
        x: (e.clientX - rect.left) * scaleX,
        y: (e.clientY - rect.top) * scaleY
    };
}

function getOverlayHandles(overlay) {
    const bounds = getOverlayBounds(overlay);
    const radius = HANDLE_SIZE / 2;
    return [
        { type: 'delete', x: bounds.x + bounds.w + radius / 2, y: bounds.y + radius / 2, size: HANDLE_SIZE },
        { type: 'resize', x: bounds.x + bounds.w + radius / 2, y: bounds.y + bounds.h + radius / 2, size: HANDLE_SIZE },
        { type: 'rotate', x: bounds.x + bounds.w / 2, y: bounds.y - radius, size: HANDLE_SIZE }
    ];
}

function handleHitTest(overlay, x, y) {
    if (!overlay) return null;
    const handles = getOverlayHandles(overlay);
    return handles.find((handle) => {
        const half = handle.size / 2 + HANDLE_HIT_EXPANSION;
        return x >= handle.x - half && x <= handle.x + half && y >= handle.y - half && y <= handle.y + half;
    }) || null;
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
    const hasSelection = Boolean(selectedOverlay);
    if (deleteOverlayBtn) deleteOverlayBtn.disabled = !hasSelection;
    if (!overlayScaleRange || !overlayRotationRange || !overlayScaleValue || !overlayRotationValue) return;
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

function deleteOverlay(target = selectedOverlay) {
    if (!target) return;
    overlays = overlays.filter((overlay) => overlay !== target);
    setSelected(null);
}

function drawHandle(handle) {
    if (!editorCtx) return;
    const radius = handle.size / 2;
    editorCtx.save();
    editorCtx.beginPath();
    editorCtx.fillStyle = 'rgba(5,5,9,0.92)';
    editorCtx.strokeStyle = handle.type === 'delete' ? '#ff6b6b' : '#c8a2ff';
    editorCtx.lineWidth = 2;
    editorCtx.arc(handle.x, handle.y, radius, 0, Math.PI * 2);
    editorCtx.fill();
    editorCtx.stroke();
    editorCtx.strokeStyle = '#fff';
    editorCtx.lineWidth = 2.2;
    editorCtx.beginPath();
    if (handle.type === 'delete') {
        editorCtx.moveTo(handle.x - 6, handle.y - 6);
        editorCtx.lineTo(handle.x + 6, handle.y + 6);
        editorCtx.moveTo(handle.x + 6, handle.y - 6);
        editorCtx.lineTo(handle.x - 6, handle.y + 6);
    } else if (handle.type === 'rotate') {
        editorCtx.arc(handle.x, handle.y, radius - 8, -Math.PI * 0.7, Math.PI * 0.6);
        editorCtx.moveTo(handle.x + (radius - 10), handle.y + 1);
        editorCtx.lineTo(handle.x + (radius - 3), handle.y + 4);
        editorCtx.lineTo(handle.x + (radius - 2), handle.y - 4);
    } else {
        editorCtx.moveTo(handle.x - 5, handle.y + 5);
        editorCtx.lineTo(handle.x + 6, handle.y - 6);
        editorCtx.moveTo(handle.x - 1, handle.y + 5);
        editorCtx.lineTo(handle.x + 6, handle.y - 2);
    }
    editorCtx.stroke();
    editorCtx.restore();
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
        const handles = getOverlayHandles(overlay);
        handles.forEach((handle) => drawHandle(handle));
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
    const baseLayer = adjustedBackgroundImage || backgroundImage;
    editorCtx.drawImage(baseLayer, 0, 0, editorCanvas.width, editorCanvas.height);
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
    resetImageAdjustments(false);
    setActiveTab('image');
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

function resetPointerState() {
    if (editorCanvas && activePointerId !== null && editorCanvas.releasePointerCapture) {
        try {
            editorCanvas.releasePointerCapture(activePointerId);
        } catch (err) {
            // Ignorieren, falls Pointer-Capture bereits freigegeben wurde.
        }
    }
    draggingOverlay = null;
    interactionMode = null;
    activePointerId = null;
    resizeStartDistance = 0;
    resizeStartScale = 1;
    rotateStartAngle = 0;
    rotateStartRotation = 0;
    dragStart = { x: 0, y: 0 };
}

function onPointerDown(e) {
    if (!editorCanvas || activePointerId !== null) return;
    activePointerId = e.pointerId;
    if (editorCanvas.setPointerCapture) {
        editorCanvas.setPointerCapture(e.pointerId);
    }
    const { x, y } = getCanvasCoordinates(e);
    const handleHit = handleHitTest(selectedOverlay, x, y);
    if (handleHit) {
        if (handleHit.type === 'delete') {
            deleteOverlay(selectedOverlay);
            resetPointerState(e);
            return;
        }
        draggingOverlay = selectedOverlay;
        dragStart = { x, y };
        if (handleHit.type === 'resize') {
            interactionMode = 'resize';
            resizeStartDistance = Math.max(12, Math.hypot(x - selectedOverlay.x, y - selectedOverlay.y));
            resizeStartScale = selectedOverlay.scale || 1;
        } else if (handleHit.type === 'rotate') {
            interactionMode = 'rotate';
            rotateStartAngle = Math.atan2(y - selectedOverlay.y, x - selectedOverlay.x);
            rotateStartRotation = selectedOverlay.rotation || 0;
        }
        e.preventDefault();
        return;
    }
    const hit = overlayHitTest(x, y);
    if (hit) {
        interactionMode = 'drag';
        draggingOverlay = hit;
        dragStart = { x, y };
        setSelected(hit);
        e.preventDefault();
    } else {
        setSelected(null);
        resetPointerState(e);
    }
}

function onPointerMove(e) {
    if (!draggingOverlay || !editorCanvas || activePointerId !== e.pointerId || !interactionMode) return;
    const { x, y } = getCanvasCoordinates(e);
    if (interactionMode === 'drag') {
        const dx = x - dragStart.x;
        const dy = y - dragStart.y;
        draggingOverlay.x += dx;
        draggingOverlay.y += dy;
        dragStart = { x, y };
    } else if (interactionMode === 'resize') {
        const distance = Math.max(12, Math.hypot(x - draggingOverlay.x, y - draggingOverlay.y));
        const factor = distance / resizeStartDistance;
        draggingOverlay.scale = clamp(resizeStartScale * factor, MIN_OVERLAY_SCALE, MAX_OVERLAY_SCALE);
        updateTransformControls();
    } else if (interactionMode === 'rotate') {
        const angle = Math.atan2(y - draggingOverlay.y, x - draggingOverlay.x);
        draggingOverlay.rotation = rotateStartRotation + (angle - rotateStartAngle);
        updateTransformControls();
    }
    renderEditor();
    e.preventDefault();
}

function onPointerUp(e) {
    if (activePointerId !== null && e?.pointerId !== undefined && e.pointerId !== activePointerId) return;
    resetPointerState();
}

function modifyScale(factor) {
    if (!selectedOverlay) return;
    selectedOverlay.scale = clamp(selectedOverlay.scale * factor, MIN_OVERLAY_SCALE, MAX_OVERLAY_SCALE);
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

async function handleFileSelection(file) {
    if (!file) return;
    if (remaining <= 0) {
        showToast('Limit erreicht.');
        return;
    }
    const allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
    const mimeType = allowedTypes.includes(file.type)
        ? file.type === 'image/jpg'
            ? 'image/jpeg'
            : file.type
        : '';
    if (!mimeType) {
        showToast('Bitte nur JPEG oder PNG auswählen.');
        return;
    }
    try {
        const loaded = await loadFileAsImage(file);
        const scaled = await scaleImageForEditor(loaded, mimeType);
        enterEditMode(scaled);
    } catch (err) {
        console.warn('Bild konnte nicht geladen werden:', err);
        showToast('Bild konnte nicht geladen werden.');
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
uploadBtn?.addEventListener('click', () => {
    if (remaining <= 0) {
        showToast('Limit erreicht.');
        return;
    }
    uploadInput?.click();
});
uploadInput?.addEventListener('change', (e) => {
    const file = e.target.files?.[0];
    if (uploadInput) uploadInput.value = '';
    handleFileSelection(file);
});
addTextBtn?.addEventListener('click', addTextOverlay);
editTextBtn?.addEventListener('click', editSelectedText);
deleteOverlayBtn?.addEventListener('click', () => deleteOverlay());
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
    selectedOverlay.scale = clamp(value / 100, MIN_OVERLAY_SCALE, MAX_OVERLAY_SCALE);
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
brightnessRange?.addEventListener('input', () => {
    const value = Number(brightnessRange.value);
    if (Number.isNaN(value)) return;
    imageAdjustments.brightness = clamp(value, -100, 100);
    updateAdjustmentUI();
    applyImageAdjustments();
});
contrastRange?.addEventListener('input', () => {
    const value = Number(contrastRange.value);
    if (Number.isNaN(value)) return;
    imageAdjustments.contrast = clamp(value, -100, 100);
    updateAdjustmentUI();
    applyImageAdjustments();
});
adjustmentResetBtn?.addEventListener('click', () => resetImageAdjustments());
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
window.addEventListener('pointercancel', onPointerUp);
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

function addUnlockedItems(items) {
    if (!Array.isArray(items)) return;
    const firstOverlaySlider = document.querySelector('[data-overlay-slider]');
    const overlayCategoryId = firstOverlaySlider?.getAttribute('data-overlay-slider') || overlayCategories[0]?.id || 'alle-overlays';

    items.forEach((item) => {
        if (item.type === 'sticker' && item.asset_path && stickerPalette) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'sticker-btn';
            btn.dataset.src = item.asset_path;
            const img = document.createElement('img');
            img.src = item.asset_path;
            img.alt = item.name || 'Sticker';
            btn.appendChild(img);
            stickerPalette.appendChild(btn);
        }

        if (item.type === 'frame' && item.asset_path && framePalette) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'sticker-btn frame-btn';
            btn.dataset.src = item.asset_path;
            const img = document.createElement('img');
            img.src = item.asset_path;
            img.alt = item.name || 'Rahmen';
            btn.appendChild(img);
            framePalette.appendChild(btn);
        }

        if (item.type === 'overlay' && item.asset_path && firstOverlaySlider) {
            const overlayId = `bonus-${item.id}`;
            overlayFilters.push({ id: overlayId, name: `${item.name || 'Overlay'} (Bonus)`, src: item.asset_path, category: overlayCategoryId });
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'overlay-thumb overlay-btn';
            btn.dataset.overlayId = overlayId;
            btn.dataset.overlayCategory = overlayCategoryId;
            btn.innerHTML = `<img src="${item.asset_path}" alt="${item.name || 'Overlay'}" /><span class="overlay-thumb-label">${item.name || 'Overlay'} (Bonus)</span>`;
            firstOverlaySlider.appendChild(btn);
            updateOverlayFilterButtons();
        }

        if (item.type === 'filter' && item.css_filter && colorFilterSelect) {
            const filterId = `bonus-${item.id}`;
            colorFilters.push({ id: filterId, name: `${item.name || 'Filter'} (Bonus)`, css: item.css_filter });
            const option = document.createElement('option');
            option.value = filterId;
            option.textContent = `${item.name || 'Filter'} (Bonus)`;
            colorFilterSelect.appendChild(option);
        }
    });
}

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
            const unlockedItems = Array.isArray(json.unlocked_items) ? json.unlocked_items : [];
            if (unlockedItems.length) {
                addUnlockedItems(unlockedItems);
                setCodeFeedback(`Bonus-Inhalte freigeschaltet (${unlockedItems.length}).`, true);
                showToast('Neue Sticker & Filter verfügbar!');
            } else {
                const granted = Number(json.extra_photos || 0);
                extraPhotos += granted;
                updateRemaining(Number(json.remaining ?? remaining));
                updateLimitsDisplay();
                setCodeFeedback(`+${granted} Fotos freigeschaltet.`, true);
                showToast('Bonus aktiviert!');
            }
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
setActiveTab('image');
setOverlayFilter(selectedOverlayFilterId);
if (!navigator.mediaDevices?.getUserMedia) {
    cameraUnavailable = true;
}
updateRemaining(remaining);
processQueue();
loadCustomFonts();
updateTransformControls();
