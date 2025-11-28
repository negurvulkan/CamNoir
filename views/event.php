<?php
ob_start();
$remaining = (int)$event['max_photos_per_session'] - (int)$session['photo_count'];
?>
<style>
:root { --primary: <?= json_encode($event['theme_primary_color'] ?: '#c8a2ff') ?>; }
</style>
<div class="header">
    <div>
        <p class="eyebrow">NRW Noir Disposable Cam</p>
        <h1><?= sanitize_text($event['name']) ?></h1>
        <p class="muted">Du kannst noch <strong id="remaining-count"><?= $remaining ?></strong> von <?= (int)$event['max_photos_per_session'] ?> Fotos aufnehmen.</p>
        <p class="muted small">Jedes Foto erhält einen Löschcode im Bild. Du kannst später mit deiner Session-ID oder einem Löschcode löschen lassen.</p>
        <p class="muted small">Event-Galerie ansehen: <a href="<?= base_url('e/' . sanitize_text($event['slug']) . '/gallery') ?>">Zur Übersicht</a></p>
    </div>
</div>

<section class="card">
    <label class="checkbox">
        <input type="checkbox" id="consent" />
        <span>Ich stimme der Verarbeitung meiner Fotos im Rahmen dieses Events zu.</span>
    </label>
    <div class="camera">
        <video id="camera-preview" playsinline autoplay muted class="preview"></video>
        <canvas id="camera-canvas" class="hidden"></canvas>
    </div>
    <div class="actions">
        <button id="start-camera" class="secondary">Kamera starten</button>
        <button id="take-photo" class="primary" disabled>Foto aufnehmen</button>
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
        remaining: <?= $remaining ?>
    };
</script>
<script src="<?= base_url('js/app.js') ?>"></script>
<?php
$content = ob_get_clean();
$title = sanitize_text($event['name']) . ' – NRW Noir Cam';
include __DIR__ . '/layout.php';
