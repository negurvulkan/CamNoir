<?php
ob_start();
$sessionExtra = (int)($session['extra_photos'] ?? 0);
$totalLimit = (int)$event['max_photos_per_session'] + $sessionExtra;
$remaining = max(0, $totalLimit - (int)$session['photo_count']);
$stickerDir = __DIR__ . '/../public/stickers';
$frameDir = __DIR__ . '/../public/frames';
$overlayDir = __DIR__ . '/../public/overlays';
$stickers = [];
$frames = [];
$overlayCategories = [];
$colorFilters = merge_color_filters($event['color_filters'] ?? null);
$unlockables = $unlockables ?? [];
$slugify = function (string $value): string {
    return trim(preg_replace('/[^A-Za-z0-9]+/', '-', strtolower($value)), '-') ?: 'item';
};
$fonts = [
    ['name' => 'Arial', 'url' => null],
];
if (is_dir($stickerDir)) {
    foreach (glob($stickerDir . '/*.{png,jpg,jpeg,svg,webp}', GLOB_BRACE) as $file) {
        $stickers[] = base_url('stickers/' . basename($file));
    }
}
$unlockedOverlays = [];
foreach ($unlockables as $item) {
    if ($item['type'] === 'sticker' && !empty($item['asset_path'])) {
        $stickers[] = base_url(ltrim($item['asset_path'], '/'));
    }
    if ($item['type'] === 'frame' && !empty($item['asset_path'])) {
        $frames[] = base_url(ltrim($item['asset_path'], '/'));
    }
    if ($item['type'] === 'overlay' && !empty($item['asset_path'])) {
        $slug = $slugify($item['name'] ?? 'overlay');
        $unlockedOverlays[] = [
            'id' => 'unlock-' . $slug,
            'name' => ($item['name'] ?? 'Overlay') . ' (Bonus)',
            'src' => base_url(ltrim($item['asset_path'], '/')),
        ];
    }
    if ($item['type'] === 'filter' && !empty($item['css_filter'])) {
        $colorFilters[] = [
            'id' => 'unlock-' . $slugify($item['name'] ?? 'filter'),
            'name' => ($item['name'] ?? 'Filter') . ' (Bonus)',
            'css' => sanitize_color_filter_css($item['css_filter']),
        ];
    }
}
$colorFilters = array_values(array_unique($colorFilters, SORT_REGULAR));
if (is_dir($frameDir)) {
    foreach (glob($frameDir . '/*.{png,jpg,jpeg,svg,webp}', GLOB_BRACE) as $file) {
        $frames[] = base_url('frames/' . basename($file));
    }
}
if (is_dir($overlayDir)) {
    $subFolders = array_filter(glob($overlayDir . '/*'), 'is_dir');
    $rootOverlays = glob($overlayDir . '/*.{png,jpg,jpeg,svg,webp}', GLOB_BRACE);

    $normalizeName = function (string $filename): string {
        $name = preg_replace('/[-_]+/', ' ', pathinfo($filename, PATHINFO_FILENAME));
        return ucwords($name ?: 'Overlay');
    };

    $buildOverlayList = function (string $dir, string $categoryId = '', string $categoryPath = '') use ($normalizeName): array {
        $items = [];
        foreach (glob($dir . '/*.{png,jpg,jpeg,svg,webp}', GLOB_BRACE) as $file) {
            $fileName = pathinfo($file, PATHINFO_FILENAME);
            $slug = trim(preg_replace('/[^A-Za-z0-9]+/', '-', strtolower($fileName)), '-');
            $items[] = [
                'id' => ($categoryId ? $categoryId . '-' : '') . ($slug ?: $fileName),
                'name' => $normalizeName($file),
                'src' => base_url('overlays/' . ($categoryPath ? $categoryPath . '/' : '') . basename($file)),
            ];
        }
        return $items;
    };

    if (!empty($subFolders)) {
        foreach ($subFolders as $folder) {
            $folderName = basename($folder);
            $categorySlug = trim(preg_replace('/[^A-Za-z0-9]+/', '-', strtolower($folderName)), '-');
            $overlays = $buildOverlayList($folder, $categorySlug ?: $folderName, $folderName);
            if (!empty($overlays)) {
                $overlayCategories[] = [
                    'id' => $categorySlug ?: $folderName,
                    'name' => ucwords(str_replace('-', ' ', $categorySlug ?: $folderName)),
                    'overlays' => $overlays,
                ];
            }
        }

        if (!empty($rootOverlays)) {
            array_unshift($overlayCategories, [
                'id' => 'alle-overlays',
                'name' => 'Alle Overlays',
                'overlays' => $buildOverlayList($overlayDir, '', ''),
            ]);
        }
    } elseif (!empty($rootOverlays)) {
        $overlayCategories[] = [
            'id' => 'alle-overlays',
            'name' => 'Alle Overlays',
            'overlays' => $buildOverlayList($overlayDir, '', ''),
        ];
    }
}
$overlayCategories = array_values($overlayCategories);
if (!empty($unlockedOverlays)) {
    $overlayCategories[] = [
        'id' => 'unlocked-overlays',
        'name' => 'Freigeschaltet',
        'overlays' => $unlockedOverlays,
    ];
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
        <p class="muted">Du kannst noch <strong id="remaining-count"><?= $remaining ?></strong> von <strong id="total-limit"><?= $totalLimit ?></strong> Fotos aufnehmen<span id="bonus-info"><?= $sessionExtra > 0 ? ' (inkl. ' . $sessionExtra . ' Bonus)' : '' ?></span>.</p>
        <p class="muted small">Event-Galerie ansehen: <a href="<?= base_url('e/' . sanitize_text($event['slug']) . '/gallery') ?>">Zur Übersicht</a></p>
    </div>
    <div class="actions">
        <button id="open-code-modal" class="secondary" type="button">Code eingeben</button>
    </div>
    <?php if (!empty($event['banner_url'])): ?>
        <img src="<?= sanitize_text($event['banner_url']) ?>" alt="Event Banner" class="event-banner">
    <?php endif; ?>
</div>

<section class="card">
    <div id="camera-view">
        <div class="camera">
            <video id="camera-preview" playsinline autoplay muted class="preview"></video>
            <canvas id="camera-canvas" class="hidden"></canvas>
        </div>
        <div class="actions">
            <button id="start-camera" class="secondary">Kamera starten</button>
            <button id="switch-camera" class="secondary" disabled>Kamera wechseln</button>
            <button id="toggle-torch" class="secondary hidden" type="button" disabled>Blitz: Aus</button>
        </div>
        <div class="actions">
            <button id="take-photo" class="primary" disabled>Foto aufnehmen</button>
            <button id="upload-photo" class="secondary" type="button">Foto hochladen</button>
            <input id="upload-input" type="file" accept="image/*" class="hidden" />
        </div>
    </div>

    <div id="editor-view" class="hidden">
        <p class="muted small">Zuerst Bild anpassen und danach Filter, Rahmen, Sticker oder Text platzieren.</p>
        <div class="editor-layout tabbed-editor">
            <div class="editor-canvas-shell">
                <div class="tab-list tab-list-floating" role="tablist">
                    <button class="tab-btn active" role="tab" aria-selected="true" data-tab-target="image">Bild</button>
                    <button class="tab-btn" role="tab" aria-selected="false" data-tab-target="filter">Filter</button>
                    <button class="tab-btn" role="tab" aria-selected="false" data-tab-target="frames">Rahmen</button>
                    <button class="tab-btn" role="tab" aria-selected="false" data-tab-target="stickers">Sticker</button>
                    <button class="tab-btn" role="tab" aria-selected="false" data-tab-target="text">Text</button>
                </div>
                <canvas id="editor-canvas"></canvas>
            </div>
            <div class="tab-shell">
                <div class="tab-panels">
                    <div class="tab-panel" data-tab-panel="image">
                        <div class="tool-header">
                            <p class="muted small">Bildanpassung</p>
                            <p class="muted small">Wird direkt auf das Foto angewendet, bevor Filter, Sticker oder Rahmen gerendert werden.</p>
                        </div>
                        <div class="panel-grid">
                            <div class="panel-card">
                                <label class="field compact">
                                    <span class="muted small">Helligkeit</span>
                                    <input id="brightness-range" type="range" min="-100" max="100" value="0" />
                                    <span class="muted small"><span id="brightness-value">0</span></span>
                                </label>
                                <p class="muted small">Dunkler bis heller. Basiswert = 0.</p>
                            </div>
                            <div class="panel-card">
                                <label class="field compact">
                                    <span class="muted small">Kontrast</span>
                                    <input id="contrast-range" type="range" min="-100" max="100" value="0" />
                                    <span class="muted small"><span id="contrast-value">0</span></span>
                                </label>
                                <p class="muted small">Flacher bis kräftiger Kontrast. Basiswert = 0.</p>
                            </div>
                            <div class="panel-card actions-row">
                                <button id="adjustment-reset-btn" class="secondary" type="button">Reset</button>
                                <p class="muted small">Setzt alle Bildanpassungen zurück.</p>
                            </div>
                        </div>
                    </div>
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
                                <div class="tool-row overlay-scope-row">
                                    <div class="tool-header">
                                        <p class="muted small">Overlay-Filter Bereich</p>
                                        <p class="muted small">Wirkt auf das Foto oder die gesamte Komposition.</p>
                                    </div>
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
                                <?php if (!empty($overlayCategories)): ?>
                                    <div class="overlay-browser">
                                        <div class="overlay-tabs" role="tablist">
                                            <?php foreach ($overlayCategories as $index => $category): ?>
                                                <button type="button"
                                                    class="overlay-tab <?= $index === 0 ? 'active' : '' ?>"
                                                    role="tab"
                                                    aria-selected="<?= $index === 0 ? 'true' : 'false' ?>"
                                                    data-overlay-category-tab="<?= sanitize_text($category['id']) ?>">
                                                    <?= sanitize_text($category['name']) ?>
                                                </button>
                                            <?php endforeach; ?>
                                        </div>
                                        <div id="overlay-filter-palette" class="overlay-slider-shell">
                                            <?php foreach ($overlayCategories as $index => $category): ?>
                                                <div class="overlay-slider <?= $index === 0 ? 'active' : 'hidden' ?>" data-overlay-slider="<?= sanitize_text($category['id']) ?>">
                                                    <button type="button" class="overlay-thumb overlay-btn" data-overlay-id="none" data-src="" data-overlay-category="<?= sanitize_text($category['id']) ?>">
                                                        <span class="overlay-thumb-label">Kein Overlay</span>
                                                    </button>
                                                    <?php foreach ($category['overlays'] as $overlay): ?>
                                                        <button type="button" class="overlay-thumb overlay-btn" data-overlay-id="<?= sanitize_text($overlay['id']) ?>" data-src="<?= sanitize_text($overlay['src']) ?>" data-overlay-category="<?= sanitize_text($category['id']) ?>">
                                                            <img src="<?= sanitize_text($overlay['src']) ?>" alt="<?= sanitize_text($overlay['name']) ?>" />
                                                            <span class="overlay-thumb-label"><?= sanitize_text($overlay['name']) ?></span>
                                                        </button>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <p class="muted small">PNG-Texturen in <code>public/overlays</code> ablegen, um sie hier auszuwählen.</p>
                                <?php endif; ?>
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
                        <div class="panel-card">
                            <p class="muted small">Auswahl löschen</p>
                            <button id="delete-overlay-btn" type="button" class="secondary danger" disabled>Ausgewähltes Objekt löschen</button>
                            <p class="muted small">Tipp: Tippe aufs ❌ am Objekt, um es noch schneller zu entfernen.</p>
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

<div id="code-modal" class="photo-modal hidden" role="dialog" aria-modal="true">
    <div class="modal-content form-modal">
        <button type="button" id="close-code-modal" aria-label="Schließen">✕</button>
        <h3>Bonus-Code einlösen</h3>
        <p class="muted small">Codes schenken dir zusätzliche Fotoslots für dieses Event.</p>
        <form id="bonus-code-form" class="grid" style="grid-template-columns: 1fr; gap: 12px;">
            <label class="field">Code
                <input type="text" id="bonus-code-input" name="code" required placeholder="FILMROLL-2024">
            </label>
            <button type="submit" class="primary">Einlösen</button>
        </form>
        <div id="code-feedback" class="alert hidden"></div>
    </div>
</div>

<div id="toast" class="toast hidden"></div>

<script>
    window.CAM_CONFIG = {
        uploadUrl: "<?= base_url('e/' . sanitize_text($event['slug']) . '/upload') ?>",
        sessionToken: "<?= sanitize_text($session['session_token']) ?>",
        remaining: <?= $remaining ?>,
        eventSlug: "<?= sanitize_text($event['slug']) ?>",
        baseLimit: <?= (int)$event['max_photos_per_session'] ?>,
        extraPhotos: <?= $sessionExtra ?>,
        redeemUrl: "<?= base_url('redeem-code') ?>",
        fonts: <?= json_encode($fonts, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
        colorFilters: <?= json_encode($colorFilters, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
        overlayCategories: <?= json_encode($overlayCategories, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>
    };
</script>
<script src="<?= base_url('js/app.js') ?>"></script>
<?php
$content = ob_get_clean();
$title = sanitize_text($event['name']) . ' – NRW Noir Cam';
include __DIR__ . '/layout.php';
