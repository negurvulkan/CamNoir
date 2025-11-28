<?php
ob_start();
?>
<h1>Foto per Löschcode löschen</h1>
<p class="muted">Du hast einen Löschcode auf einem Bild entdeckt? Gib ihn hier ein, um das Foto zu entfernen.</p>
<form method="POST" class="card">
    <label class="field">Löschcode
        <input type="text" name="delete_code" value="<?= isset($prefill_code) ? sanitize_text($prefill_code) : '' ?>" required minlength="4" maxlength="12">
    </label>
    <button type="submit" class="primary">Löschen</button>
</form>
<?php if (isset($status)): ?>
    <?php if ($status === 'deleted'): ?>
        <div class="alert success">Das Foto wurde gelöscht.</div>
    <?php else: ?>
        <div class="alert warning">Kein Foto zu diesem Code gefunden.</div>
    <?php endif; ?>
<?php endif; ?>
<?php
$content = ob_get_clean();
$title = 'Löschcode verwenden';
include __DIR__ . '/layout.php';
