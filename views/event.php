<?php
ob_start();
$remaining = (int)$event['max_photos_per_session'] - (int)$session['photo_count'];
$stickerDir = __DIR__ . '/../public/stickers';
$frameDir = __DIR__ . '/../public/frames';
$overlayDir = __DIR__ . '/../public/overlays';
$stickers = [];
$frames = [];
$overlayFilters = [];
$colorFilters = [
    ['id' => 'none', 'name' => 'Kein Filter', 'css' => 'none'],
    ['id' => 'noir-classic', 'name' => 'Noir Classic', 'css' => 'grayscale(1) contrast(1.12) brightness(0.96)'],
    ['id' => 'noir-punch', 'name' => 'Noir Punch', 'css' => 'grayscale(0.85) contrast(1.24) brightness(0.94) saturate(0.9)'],
    ['id' => 'noir-soft', 'name' => 'Noir Soft', 'css' => 'grayscale(1) contrast(1.05) brightness(1.02) saturate(0.8)'],
    ['id' => 'noir-warm', 'name' => 'Warm Noir', 'css' => 'grayscale(0.8) sepia(0.12) contrast(1.1) brightness(0.98)'],
];
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
        <div class="editor-layout">
            <canvas id="editor-canvas"></canvas>
            <div id="editor-tools">
                <div class="tool-header">
                    <p class="muted small">Farbfilter (Foto)</p>
                    <p class="muted small">Wirken nur auf das Basisfoto vor Stickern, Text und Rahmen.</p>
                </div>
                <div class="tool-row">
                    <select id="color-filter-select" class="font-select">
                        <?php foreach ($colorFilters as $filter): ?>
                            <option value="<?= sanitize_text($filter['id']) ?>"><?= sanitize_text($filter['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="tool-header">
                    <p class="muted small">Overlay-Filter (PNG-Texturen)</p>
                    <p class="muted small">Wahlweise nur über dem Foto oder über der gesamten Komposition.</p>
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
                    <label class="radio">
                        <input type="radio" name="overlay-scope" value="photo" checked />
                        <span>Nur Foto</span>
                    </label>
                    <label class="radio">
                        <input type="radio" name="overlay-scope" value="composition" />
                        <span>Gesamte Komposition</span>
                    </label>
                </div>
                <div class="tool-header">
                    <p class="muted small">Rahmen hinzufügen</p>
                    <p class="muted small">Wähle einen Rahmen, der über dem Foto liegt.</p>
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
                <div class="tool-header">
                    <p class="muted small">Sticker hinzufügen</p>
                    <p class="muted small">Tipp: Ziehe Sticker/Text, um sie zu verschieben.</p>
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
                <div class="tool-header">
                    <p class="muted small">Schriftart wählen</p>
                    <p class="muted small">.ttf-Dateien können in <code>public/fonts</code> abgelegt werden.</p>
                </div>
                <div class="tool-row">
                    <select id="font-select" class="font-select">
                        <?php foreach ($fonts as $font): ?>
                            <option value="<?= sanitize_text($font['name']) ?>"><?= sanitize_text($font['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="tool-row">
                    <button id="add-text-btn" class="secondary" type="button">Text hinzufügen</button>
                    <div class="transform-buttons">
                        <button id="overlay-scale-down" type="button" class="secondary" title="Kleiner">➖</button>
                        <button id="overlay-scale-up" type="button" class="secondary" title="Größer">➕</button>
                        <button id="overlay-rotate-left" type="button" class="secondary" title="Links drehen">⟲</button>
                        <button id="overlay-rotate-right" type="button" class="secondary" title="Rechts drehen">⟳</button>
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
