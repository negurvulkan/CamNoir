<?php ob_start(); ?>
<h1>Admin Login</h1>
<form method="POST" class="card">
    <label class="field">Passwort
        <input type="password" name="password" required>
    </label>
    <button type="submit" class="primary">Login</button>
</form>
<?php if (!empty($error)): ?>
    <div class="alert warning"><?= sanitize_text($error) ?></div>
<?php endif; ?>
<?php
$content = ob_get_clean();
$title = 'Admin Login';
include __DIR__ . '/layout.php';
