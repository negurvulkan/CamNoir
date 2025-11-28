<?php ob_start(); ?>
<div class="flex-between">
    <h1>Fotos für <?= sanitize_text($event['name']) ?></h1>
    <a href="<?= base_url('admin/events') ?>" class="secondary">Zurück</a>
</div>
<section class="card">
    <form method="POST" class="inline-form">
        <input type="hidden" name="action" value="delete">
        <label class="field">Löschcode manuell löschen
            <input type="text" name="delete_code" placeholder="ABC123">
        </label>
        <button class="primary" type="submit">Löschen</button>
    </form>
</section>
<section class="card">
    <form method="POST">
        <input type="hidden" name="action" value="export">
        <div class="flex-between">
            <h2>Foto-Export</h2>
            <div class="actions">
                <button class="secondary" type="submit" name="export_all" value="1">Alle als ZIP</button>
                <button class="primary" type="submit">Auswahl exportieren</button>
            </div>
        </div>
        <div class="grid photos">
        <?php foreach ($photos as $photo): ?>
            <label class="photo" style="display:block;">
                <input type="checkbox" name="photo_ids[]" value="<?= (int)$photo['id'] ?>">
                <img src="<?= base_url(str_replace(__DIR__ . '/../', '', $photo['file_path'])) ?>" alt="Foto">
                <p class="muted small">Session: <code><?= sanitize_text($photo['session_id']) ?></code></p>
                <p class="muted small">Löschcode: <code><?= sanitize_text($photo['delete_code']) ?></code></p>
                <p class="muted small">Status: <?= $photo['deleted_at'] ? 'Gelöscht' : ((int)$photo['is_approved'] ? 'Freigegeben' : 'Wartet auf Freigabe') ?></p>
                <p class="muted small">Erstellt: <?= sanitize_text($photo['created_at']) ?></p>
                <div class="actions">
                    <form method="POST" style="display:flex; gap:8px; align-items:center;">
                        <input type="hidden" name="action" value="approve">
                        <input type="hidden" name="photo_id" value="<?= (int)$photo['id'] ?>">
                        <button class="secondary" type="submit" name="state" value="1" <?= (int)$photo['is_approved'] ? 'disabled' : '' ?>>Freigeben</button>
                        <button class="secondary" type="submit" name="state" value="0" <?= !(int)$photo['is_approved'] ? 'disabled' : '' ?>>Sperren</button>
                    </form>
                </div>
            </label>
        <?php endforeach; ?>
        </div>
    </form>
</section>
<?php
$content = ob_get_clean();
$title = 'Admin Fotos';
include __DIR__ . '/layout.php';
