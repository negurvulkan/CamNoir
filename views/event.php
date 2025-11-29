<?php
ob_start();
$remaining = (int)$event['max_photos_per_session'] - (int)$session['photo_count'];
$stickerDir = __DIR__ . '/../public/stickers';
$frameDir = __DIR__ . '/../public/frames';
$overlayDir = __DIR__ . '/../public/overlays';
$stickers = [];
$frames = [];
$overlayFilters = [];
$colorFilters = merge_color_filters($event['color_filters'] ?? null);
$fonts = [
    ['name' => 'Arial', 'url' => null],
];
if (is_dir($stickerDir)) {
    foreach (glob($stickerDir . '/*.{png,jpg,jpeg,svg,webp}', GLOB_BRACE) as $file) {
        $stickers[] = base_url('stickers/' . basename($file));
    }
}
if (is_dir($frameDir)) {
    foreach (glob($frameDir . '/*.{png,jpg,jpeg,svg,webp}', GLOB_BRACE) as $file) {
        $frames[] = base_url('frames/' . basename($file));
    }
}
if (is_dir($overlayDir)) {
    foreach (glob($overlayDir . '/*.{png,jpg,jpeg,svg,webp}', GLOB_BRACE) as $file) {
        $name = preg_replace('/[-_]+/', ' ', pathinfo($file, PATHINFO_FILENAME));
        $overlayFilters[] = [
            'id' => pathinfo($file, PATHINFO_FILENAME),
            'name' => ucwords($name ?: 'Overlay'),
            'src' => base_url('overlays/' . basename($file)),
        ];
    }
}
$fontDir = __DIR__ . '/../public/fonts';
if (is_dir($fontDir)) {
    foreach (glob($fontDir . '/*.ttf') as $file) {
        $fontName = preg_replace('/[^A-Za-z0-9 _-]/', '', pathinfo($file, PATHINFO_FILENAME));
        if (!$fontName) {
            $fontName = pathinfo($file, PATHINFO_FILENAME);
        }
        $fonts[] = [
            'name' => $fontName,
            'url' => base_url('fonts/' . basename($file)),
        ];
    }
}
$theme = merge_theme_settings($event['theme_settings'] ?? null);
$themeStyles = theme_style_block($theme);
?>
<div class="header">
    <div>
        <p class="eyebrow">NRW Noir Disposable Cam</p>
        <h1><?= sanitize_text($event['name']) ?></h1>
        <p class="muted">Du kannst noch <strong id="remaining-count"><?= $remaining ?></strong> von <?= (int)$event['max_photos_per_session'] ?> Fotos aufnehmen.</p>
        <p class="muted small">Event-Galerie ansehen: <a href="<?= base_url('e/' . sanitize_text($event['slug']) . '/gallery') ?>">Zur Übersicht</a></p>
    </div>
    <?php if (!empty($event['banner_url'])): ?>
        <img src="<?= sanitize_text($event['banner_url']) ?>" alt="Event Banner" class="event-banner">
    <?php endif; ?>
</div>

<section class="card">
    <label class="checkbox">
        <input type="checkbox" id="consent" />
        <span>Ich stimme der Verarbeitung meiner Fotos im Rahmen dieses Events zu.</span>
    </label>
    <div id="camera-view">
        <div class="camera">
            <video id="camera-preview" playsinline autoplay muted class="preview"></video>
            <canvas id="camera-canvas" class="hidden"></canvas>
        </div>
        <div class="actions">
            <button id="start-camera" class="secondary">Kamera starten</button>
            <button id="switch-camera" class="secondary" disabled>Kamera wechseln</button>
            <button id="take-photo" class="primary" disabled>Foto aufnehmen</button>
        </div>
    </div>

    <div id="editor-view" class="hidden">
        <div class="editor-header">
            <div>
                <p class="eyebrow">Bildeditor</p>
                <p class="muted small">Zuerst Filter wählen, dann Rahmen, Sticker oder Text platzieren.</p>
            </div>
            <div class="editor-badge">
                <span class="dot"></span>
                <span>Live-Editing aktiv</span>
            </div>
        </div>
        <div class="editor-layout tabbed-editor">
            <div class="editor-canvas-shell">
                <div class="tab-list tab-list-floating" role="tablist">
                    <button class="tab-btn active" role="tab" aria-selected="true" data-tab-target="filter">Filter</button>
                    <button class="tab-btn" role="tab" aria-selected="false" data-tab-target="frames">Rahmen</button>
                    <button class="tab-btn" role="tab" aria-selected="false" data-tab-target="stickers">Sticker</button>
                    <button class="tab-btn" role="tab" aria-selected="false" data-tab-target="text">Text</button>
                </div>
                <div class="canvas-top">
                    <p class="muted small">Overlay-Filter</p>
                    <div class="pill-group">
                        <label class="pill">
                            <input type="radio" name="overlay-scope" value="photo" checked />
                            <span>Nur Foto</span>
                        </label>
                        <label class="pill">
                            <input type="radio" name="overlay-scope" value="composition" />
                            <span>Gesamte Komposition</span>
                        </label>
                    </div>
                </div>
                <canvas id="editor-canvas"></canvas>
            </div>
            <div class="tab-shell">
                <div class="tab-panels">
                    <div class="tab-panel" data-tab-panel="filter">
                        <div class="panel-grid">
                            <div class="panel-card">
                                <div class="tool-header">
                                    <p class="muted small">Farbfilter (Foto)</p>
                                    <p class="muted small">Wirken nur auf das Basisfoto vor Stickern, Text und Rahmen.</p>
                                </div>
                                <select id="color-filter-select" class="font-select">
                                    <?php foreach ($colorFilters as $filter): ?>
                                        <option value="<?= sanitize_text($filter['id']) ?>"><?= sanitize_text($filter['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="panel-card">
                                <div class="tool-header">
                                    <p class="muted small">Overlay-Filter (PNG-Texturen)</p>
                                    <p class="muted small">Kann über dem Foto oder der ganzen Komposition liegen.</p>
                                </div>
                                <div id="overlay-filter-palette" class="sticker-palette overlay-palette">
                                    <button type="button" class="sticker-btn overlay-btn" data-overlay-id="none">Kein Overlay</button>
                                    <?php if (!empty($overlayFilters)): ?>
                                        <?php foreach ($overlayFilters as $overlay): ?>
                                            <button type="button" class="sticker-btn overlay-btn" data-overlay-id="<?= sanitize_text($overlay['id']) ?>" data-src="<?= sanitize_text($overlay['src']) ?>">
                                                <img src="<?= sanitize_text($overlay['src']) ?>" alt="<?= sanitize_text($overlay['name']) ?>" />
                                            </button>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <p class="muted small">PNG-Texturen in <code>public/overlays</code> ablegen, um sie hier auszuwählen.</p>
                                    <?php endif; ?>
                                </div>
                                <div class="tool-row overlay-scope-row">
                                    <label class="field compact">
                                        <span class="muted small">Blend Mode</span>
                                        <select id="overlay-blend-select" class="font-select">
                                            <option value="screen">Screen</option>
                                            <option value="multiply">Multiply</option>
                                            <option value="overlay">Overlay</option>
                                            <option value="lighten">Lighten</option>
                                            <option value="darken">Darken</option>
                                        </select>
                                    </label>
                                    <label class="field compact">
                                        <span class="muted small">Transparenz</span>
                                        <input id="overlay-opacity" type="range" min="0" max="100" value="80" />
                                        <span class="muted small"><span id="overlay-opacity-value">80</span>%</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="tab-panel hidden" data-tab-panel="frames">
                        <div class="tool-header">
                            <p class="muted small">Rahmen hinzufügen</p>
                            <p class="muted small">Wähle einen Rahmen, der über dem Foto liegt. "Kein Rahmen" ist immer verfügbar.</p>
                        </div>
                        <div id="frame-palette" class="sticker-palette frame-palette">
                            <button type="button" class="sticker-btn frame-btn no-frame" data-clear-frame="true">Kein Rahmen</button>
                            <?php if (!empty($frames)): ?>
                                <?php foreach ($frames as $frame): ?>
                                    <button type="button" class="sticker-btn frame-btn" data-src="<?= sanitize_text($frame) ?>">
                                        <img src="<?= sanitize_text($frame) ?>" alt="Rahmen" />
                                    </button>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="muted small">Noch keine Rahmen hochgeladen.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="tab-panel hidden" data-tab-panel="stickers">
                        <div class="tool-header">
                            <p class="muted small">Sticker hinzufügen</p>
                            <p class="muted small">Sticker durch Ziehen positionieren, Drehung und Größe über die Regler.</p>
                        </div>
                        <div id="sticker-palette" class="sticker-palette">
                            <?php if (!empty($stickers)): ?>
                                <?php foreach ($stickers as $sticker): ?>
                                    <button type="button" class="sticker-btn" data-src="<?= sanitize_text($sticker) ?>">
                                        <img src="<?= sanitize_text($sticker) ?>" alt="Sticker" />
                                    </button>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="muted small">Noch keine Sticker hochgeladen.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="tab-panel hidden" data-tab-panel="text">
                        <div class="tool-header">
                            <p class="muted small">Text hinzufügen &amp; bearbeiten</p>
                            <p class="muted small">Schrift wählen, Text platzieren und danach Größe, Rotation &amp; Position anpassen.</p>
                        </div>
                        <div class="panel-grid">
                            <div class="panel-card">
                                <p class="muted small">Schriftart</p>
                                <p class="muted small">.ttf-Dateien können in <code>public/fonts</code> abgelegt werden.</p>
                                <select id="font-select" class="font-select">
                                    <?php foreach ($fonts as $font): ?>
                                        <option value="<?= sanitize_text($font['name']) ?>"><?= sanitize_text($font['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="panel-card actions-row">
                                <button id="add-text-btn" class="secondary" type="button">Text hinzufügen</button>
                                <button id="edit-text-btn" class="secondary" type="button">Text bearbeiten</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="transform-panel" class="transform-panel hidden">
                    <div class="tool-header">
                        <p class="muted small">Größe / Rotation / Position</p>
                        <p class="muted small">Gilt für den aktuell ausgewählten Sticker oder Text (Tip: aufs Canvas tippen, um zu markieren).</p>
                    </div>
                    <div class="panel-grid compact-grid">
                        <div class="panel-card">
                            <label class="field compact">
                                <span class="muted small">Größe</span>
                                <input id="overlay-scale-range" type="range" min="20" max="400" value="100" />
                                <span class="muted small"><span id="overlay-scale-value">100</span>%</span>
                            </label>
                            <label class="field compact">
                                <span class="muted small">Rotation</span>
                                <input id="overlay-rotation-range" type="range" min="-180" max="180" value="0" />
                                <span class="muted small"><span id="overlay-rotation-value">0</span>°</span>
                            </label>
                        </div>
                        <div class="panel-card">
                            <p class="muted small">Feinjustierung</p>
                            <div class="transform-buttons">
                                <button id="overlay-scale-down" type="button" class="secondary" title="Kleiner">➖</button>
                                <button id="overlay-scale-up" type="button" class="secondary" title="Größer">➕</button>
                                <button id="overlay-rotate-left" type="button" class="secondary" title="Links drehen">⟲</button>
                                <button id="overlay-rotate-right" type="button" class="secondary" title="Rechts drehen">⟳</button>
                            </div>
                            <p class="muted small">Position per Drag &amp; Drop direkt im Canvas.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div id="editor-actions" class="actions">
            <button id="edit-cancel-btn" class="secondary" type="button">Zurück zur Kamera</button>
            <button id="edit-confirm-btn" class="primary" type="button">Fertig &amp; hochladen</button>
        </div>
    </div>

    <p class="muted small">Datenschutz? <a href="<?= base_url('privacy') ?>">Zur Datenschutzerklärung</a></p>
    <p class="muted small">Session-ID: <code><?= sanitize_text($session['session_token']) ?></code></p>
    <div id="upload-status" class="upload-status hidden">
        <span id="upload-status-text">Foto wird hochgeladen…</span>
    </div>
</section>

<div id="toast" class="toast hidden"></div>

<script>
    window.CAM_CONFIG = {
        uploadUrl: "<?= base_url('e/' . sanitize_text($event['slug']) . '/upload') ?>",
        sessionToken: "<?= sanitize_text($session['session_token']) ?>",
        remaining: <?= $remaining ?>,
        fonts: <?= json_encode($fonts, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
        colorFilters: <?= json_encode($colorFilters, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
        overlayFilters: <?= json_encode($overlayFilters, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>
    };
</script>
<script src="<?= base_url('js/app.js') ?>"></script>
<?php
$content = ob_get_clean();
$title = sanitize_text($event['name']) . ' – NRW Noir Cam';
include __DIR__ . '/layout.php';
