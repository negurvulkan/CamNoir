<?php ob_start(); ?>
<div class="flex-between">
    <h1>Bonus-Codes – <?= sanitize_text($event['name']) ?></h1>
    <a class="secondary" href="<?= base_url('admin/events/' . (int)$event['id']) ?>">Zurück</a>
</div>

<section class="card">
    <h2>Code anlegen/aktualisieren</h2>
    <form method="POST" class="grid" id="code-form">
        <input type="hidden" name="id" id="code-id" value="">
        <label class="field">Code
            <input type="text" name="code" id="code-value" required placeholder="BSP-2024">
        </label>
        <label class="field">Beschreibung (optional)
            <input type="text" name="description" id="code-description" placeholder="Filmrolle für VIP">
        </label>
        <label class="field">Zusätzliche Fotos
            <input type="number" name="extra_photos" id="code-extra" min="1" value="1" required>
        </label>
        <label class="field">Typ
            <select name="type" id="code-type">
                <option value="single_use">Single Use (einmalig gesamt)</option>
                <option value="per_session">Per Session (pro Gerät einmal)</option>
                <option value="unlimited">Unlimited (beliebig oft)</option>
            </select>
        </label>
        <label class="field">Maximale Einlösungen (optional)
            <input type="number" name="max_uses" id="code-max-uses" min="1" placeholder="leer = unbegrenzt">
        </label>
        <label class="field">Ablaufdatum (optional)
            <input type="datetime-local" name="expires_at" id="code-expires">
        </label>
        <button class="primary" type="submit">Speichern</button>
    </form>
</section>

<section class="card">
    <h2>Aktive Codes</h2>
    <div class="table-wrapper">
        <table>
            <thead><tr><th>Code</th><th>Beschreibung</th><th>Fotos</th><th>Typ</th><th>Max</th><th>Genutzt</th><th>läuft ab</th><th>Aktionen</th></tr></thead>
            <tbody>
            <?php foreach ($codes as $code): ?>
                <?php $expires = $code['expires_at'] ? str_replace(' ', 'T', $code['expires_at']) : ''; ?>
                <tr>
                    <td><code><?= sanitize_text($code['code']) ?></code></td>
                    <td><?= $code['description'] ? sanitize_text($code['description']) : '–' ?></td>
                    <td>+<?= (int)$code['extra_photos'] ?></td>
                    <td><?= sanitize_text($code['type']) ?></td>
                    <td><?= $code['max_uses'] !== null ? (int)$code['max_uses'] : '∞' ?></td>
                    <td><?= (int)$code['used_count'] ?></td>
                    <td><?= $code['expires_at'] ? sanitize_text($code['expires_at']) : '–' ?></td>
                    <td class="actions">
                        <button class="secondary" onclick='fillCode(<?= json_encode($code) ?>, "<?= sanitize_text($expires) ?>")'>Bearbeiten</button>
                        <form method="POST" style="display:inline" onsubmit="return confirm('Code wirklich löschen?');">
                            <input type="hidden" name="id" value="<?= (int)$code['id'] ?>">
                            <input type="hidden" name="action" value="delete">
                            <button type="submit" class="secondary">Löschen</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="card">
    <h2>Nutzung (Sessions)</h2>
    <div class="table-wrapper">
        <table>
            <thead><tr><th>Code</th><th>Session</th><th>Eingelöst am</th></tr></thead>
            <tbody>
            <?php foreach ($redemptions as $row): ?>
                <tr>
                    <td><code><?= sanitize_text($row['code']) ?></code></td>
                    <td><code><?= $row['session_token'] ? sanitize_text($row['session_token']) : '–' ?></code></td>
                    <td><?= sanitize_text($row['redeemed_at']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<script>
function fillCode(code, expiresValue) {
    document.getElementById('code-id').value = code.id;
    document.getElementById('code-value').value = code.code;
    document.getElementById('code-description').value = code.description || '';
    document.getElementById('code-extra').value = code.extra_photos;
    document.getElementById('code-type').value = code.type;
    document.getElementById('code-max-uses').value = code.max_uses !== null ? code.max_uses : '';
    document.getElementById('code-expires').value = expiresValue || '';
    window.scrollTo({top: 0, behavior: 'smooth'});
}
</script>
<?php
$content = ob_get_clean();
$title = 'Bonus-Codes – ' . sanitize_text($event['name']);
include __DIR__ . '/layout.php';
