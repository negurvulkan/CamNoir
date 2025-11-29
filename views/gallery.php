<?php ob_start();
$theme = merge_theme_settings($event['theme_settings'] ?? null);
$themeStyles = theme_style_block($theme);
?>
<div class="flex-between header">
    <div>
        <p class="eyebrow">NRW Noir Disposable Cam</p>
        <h1><?= sanitize_text($event['name']) ?> – Galerie</h1>
        <p class="muted">Aktuelle Event-Eindrücke. Jeder Rahmen zeigt den Löschcode direkt im Bild.</p>
    </div>
    <a class="secondary button-link" href="<?= base_url('e/' . sanitize_text($event['slug'])) ?>">Zur Kamera</a>
</div>

<?php if (!empty($event['banner_url'])): ?>
    <img src="<?= sanitize_text($event['banner_url']) ?>" alt="Event Banner" class="event-banner">
<?php endif; ?>

<section class="card">
    <p class="muted small">Du kannst Fotos auch mit einem Löschcode über <a href="<?= base_url('delete-photo') ?>">diese Seite</a> entfernen. Für hochgeladene Sessions bleibt der Aufruf der Kamera über denselben QR-Code bestehen.</p>
</section>

<?php if (empty($photos)): ?>
    <section class="card">
        <p class="muted">Noch keine Fotos vorhanden. Sei die erste Person mit einem Schnappschuss!</p>
    </section>
<?php else: ?>
    <section class="grid photos gallery-grid">
        <?php foreach ($photos as $photo): ?>
            <?php $path = base_url(str_replace(__DIR__ . '/../', '', $photo['file_path'])); ?>
            <figure class="photo">
                <img src="<?= $path ?>" alt="Event-Foto">
                <figcaption class="muted small">
                    Hochgeladen: <?= sanitize_text($photo['created_at']) ?><br><br><br>
                    <a href="<?= base_url('delete-photo?delete_code=' . urlencode($photo['delete_code'])) ?>" class="secondary button-link small">Löschantrag</a>
                </figcaption>
            </figure>
        <?php endforeach; ?>
    </section>
<?php endif; ?>

<div id="photo-modal" class="photo-modal hidden" role="dialog" aria-modal="true" aria-label="Foto Großansicht">
    <div class="modal-content">
        <img id="photo-modal-image" src="" alt="Vergrößertes Event-Foto">
        <button id="photo-modal-close" aria-label="Schließen">×</button>
    </div>
</div>

<script src="<?= base_url('js/gallery.js') ?>"></script>
<?php
$content = ob_get_clean();
$title = sanitize_text($event['name']) . ' – Galerie';
include __DIR__ . '/layout.php';
