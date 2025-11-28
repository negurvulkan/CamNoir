<?php ob_start(); ?>
<div class="flex-between">
    <h1>Fotos für <?= sanitize_text($event['name']) ?></h1>
    <a href="<?= base_url('admin/events') ?>" class="secondary">Zurück</a>
</div>
<section class="card">
    <form method="POST" class="inline-form">
        <label class="field">Löschcode manuell löschen
            <input type="text" name="delete_code" placeholder="ABC123">
        </label>
        <button class="primary" type="submit">Löschen</button>
    </form>
</section>
<section class="grid photos">
<?php foreach ($photos as $photo): ?>
    <div class="photo">
        <img src="<?= base_url(str_replace(__DIR__ . '/../', '', $photo['file_path'])) ?>" alt="Foto">
        <p class="muted small">Session: <code><?= sanitize_text($photo['session_id']) ?></code></p>
        <p class="muted small">Löschcode: <code><?= sanitize_text($photo['delete_code']) ?></code></p>
        <p class="muted small">Erstellt: <?= sanitize_text($photo['created_at']) ?></p>
    </div>
<?php endforeach; ?>
</section>
<?php
$content = ob_get_clean();
$title = 'Admin Fotos';
include __DIR__ . '/layout.php';
