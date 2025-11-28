<?php
ob_start();
?>
<h1>Fotos per Session löschen</h1>
<p class="muted">Gib deine Session-ID ein, um alle zugehörigen Fotos zu löschen.</p>
<form method="POST" class="card">
    <label class="field">Session-ID
        <input type="text" name="session_token" required minlength="6" maxlength="64">
    </label>
    <button type="submit" class="primary">Löschen</button>
</form>
<?php if (isset($status)): ?>
    <?php if ($status === 'deleted'): ?>
        <div class="alert success">Alle Fotos dieser Session wurden gelöscht.</div>
    <?php else: ?>
        <div class="alert warning">Es wurden keine Fotos zu dieser Session gefunden.</div>
    <?php endif; ?>
<?php endif; ?>
<?php
$content = ob_get_clean();
$title = 'Session löschen';
include __DIR__ . '/layout.php';
