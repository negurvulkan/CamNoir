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
    <p class="muted small">Du kannst Fotos auch mit einem Löschcode über <a href="<?= base_url('delete-photo') ?>">diese Seite</a> melden. Für hochgeladene Sessions bleibt der Aufruf der Kamera über denselben QR-Code bestehen.</p>
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
                    <button type="button" class="secondary button-link small delete-request" data-delete-code="<?= sanitize_text($photo['delete_code']) ?>">Löschantrag stellen</button>
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

<div id="delete-request-modal" class="photo-modal hidden" role="dialog" aria-modal="true" aria-label="Löschantrag stellen">
    <div class="modal-content form-modal">
        <div class="flex-between" style="align-items:flex-start;">
            <div>
                <h2 style="margin:0 0 4px;">Löschantrag stellen</h2>
                <p class="muted small" style="margin:0;">Das Foto wird bis zur Prüfung ausgeblendet.</p>
            </div>
            <button id="delete-request-close" aria-label="Schließen">×</button>
        </div>
        <form id="delete-request-form" action="<?= base_url('api/delete-requests') ?>" method="POST" style="margin-top:12px; display:flex; flex-direction:column; gap:10px;">
            <input type="hidden" name="delete_code" id="delete-request-code">
            <label class="field">Grund (optional)
                <select name="reason" id="delete-request-reason">
                    <option value="">Bitte auswählen</option>
                    <option value="privacy">Ich bin auf dem Bild zu sehen</option>
                    <option value="sensitive">Unangemessene Inhalte</option>
                    <option value="copyright">Urheberrechtliche Gründe</option>
                    <option value="other">Sonstiges</option>
                </select>
            </label>
            <label class="field">Details (optional)
                <textarea name="note" id="delete-request-note" rows="3" maxlength="500" placeholder="Beschreibe dein Anliegen"></textarea>
            </label>
            <div class="actions" style="justify-content:flex-end;">
                <button type="button" class="secondary" id="delete-request-cancel">Abbrechen</button>
                <button type="submit" class="primary">Antrag senden</button>
            </div>
            <p class="muted small" id="delete-request-status" aria-live="polite"></p>
        </form>
    </div>
</div>

<script src="<?= base_url('js/gallery.js') ?>"></script>
<?php
$content = ob_get_clean();
$title = sanitize_text($event['name']) . ' – Galerie';
include __DIR__ . '/layout.php';
