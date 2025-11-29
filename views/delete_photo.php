<?php
ob_start();
?>
<h1>Foto per Löschcode melden</h1>
<p class="muted">Du hast einen Löschcode auf einem Bild entdeckt? Stell einen Löschantrag. Das Foto verschwindet sofort aus der öffentlichen Galerie und wird vom Team geprüft.</p>
<form method="POST" class="card">
    <label class="field">Löschcode
        <input type="text" name="delete_code" value="<?= isset($prefill_code) ? sanitize_text($prefill_code) : '' ?>" required minlength="4" maxlength="12">
    </label>
    <label class="field">Grund (optional)
        <select name="reason">
            <option value="">Bitte auswählen</option>
            <option value="privacy">Ich bin auf dem Bild zu sehen</option>
            <option value="sensitive">Unangemessene Inhalte</option>
            <option value="copyright">Urheberrechtliche Gründe</option>
            <option value="other">Sonstiges</option>
        </select>
    </label>
    <label class="field">Details (optional)
        <textarea name="note" rows="3" maxlength="500" placeholder="Beschreibe dein Anliegen"></textarea>
    </label>
    <button type="submit" class="primary">Antrag senden</button>
</form>
<?php if (isset($status)): ?>
    <?php if ($status === 'pending'): ?>
        <div class="alert success">Dein Löschantrag ist eingegangen. Das Foto bleibt ausgeblendet, bis eine Entscheidung vorliegt.</div>
    <?php elseif ($status === 'approved'): ?>
        <div class="alert success">Der Löschantrag wurde bereits bestätigt und das Foto entfernt.</div>
    <?php else: ?>
        <div class="alert warning">Kein Foto zu diesem Code gefunden.</div>
    <?php endif; ?>
<?php endif; ?>
<?php
$content = ob_get_clean();
$title = 'Löschcode verwenden';
include __DIR__ . '/layout.php';
