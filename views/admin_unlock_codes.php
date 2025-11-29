<?php ob_start(); ?>
<div class="flex-between">
    <h1>Item-Codes</h1>
    <div class="flex" style="gap: 10px;">
        <a class="secondary" href="<?= base_url('admin/unlock-items') ?>">Items</a>
        <a class="secondary" href="<?= base_url('admin/events') ?>">Events</a>
    </div>
</div>

<section class="card">
    <h2>Code anlegen/aktualisieren</h2>
    <form method="POST" class="grid" id="unlock-code-form">
        <input type="hidden" name="id" id="code-id" value="">
        <label class="field">Code
            <input type="text" name="code" id="code-value" required placeholder="ITEM-2024">
        </label>
        <label class="field">Beschreibung (optional)
            <input type="text" name="description" id="code-description" placeholder="Sticker-Set für VIPs">
        </label>
        <label class="field">Typ
            <select name="type" id="code-type">
                <option value="single_use">Single Use (einmalig gesamt)</option>
                <option value="per_session">Per Session (pro Gerät einmal)</option>
                <option value="unlimited">Unlimited (beliebig oft)</option>
            </select>
        </label>
        <label class="field">Event (leer = global)
            <select name="event_id" id="code-event">
                <option value="">Global</option>
                <?php foreach ($events as $event): ?>
                    <option value="<?= (int)$event['id'] ?>"><?= sanitize_text($event['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="field">Ablaufdatum (optional)
            <input type="datetime-local" name="expires_at" id="code-expires">
        </label>
        <label class="field" style="grid-column: 1 / -1;">
            Items (Mehrfachauswahl möglich)
            <select name="item_ids[]" id="code-items" multiple size="6">
                <?php foreach ($items as $item): ?>
                    <?php $label = ($item['event_id'] ? ($item['event_name'] ?? 'Event #' . $item['event_id']) . ' – ' : 'Global – ') . $item['name']; ?>
                    <option value="<?= (int)$item['id'] ?>"><?= sanitize_text($label) ?></option>
                <?php endforeach; ?>
            </select>
            <p class="muted small">Halte STRG/CMD gedrückt, um mehrere Items auszuwählen.</p>
        </label>
        <button class="primary" type="submit">Speichern</button>
    </form>
</section>

<section class="card">
    <h2>Codes</h2>
    <div class="table-wrapper">
        <table>
            <thead><tr><th>Code</th><th>Beschreibung</th><th>Typ</th><th>Event</th><th>Items</th><th>Genutzt</th><th>läuft ab</th><th>Aktionen</th></tr></thead>
            <tbody>
            <?php foreach ($codes as $code): ?>
                <?php $expires = $code['expires_at'] ? str_replace(' ', 'T', $code['expires_at']) : ''; ?>
                <tr>
                    <td><code><?= sanitize_text($code['code']) ?></code></td>
                    <td><?= $code['description'] ? sanitize_text($code['description']) : '–' ?></td>
                    <td><?= sanitize_text($code['type']) ?></td>
                    <td><?= $code['event_id'] ? sanitize_text($code['event_name'] ?? ('#' . $code['event_id'])) : 'Global' ?></td>
                    <td>
                        <?php if (!empty($code['items'])): ?>
                            <ul class="list-plain">
                            <?php foreach ($code['items'] as $item): ?>
                                <li><?= sanitize_text($item['name']) ?> (<?= sanitize_text($item['type']) ?>)</li>
                            <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            –
                        <?php endif; ?>
                    </td>
                    <td><?= isset($code['usage_count']) ? (int)$code['usage_count'] : 0 ?></td>
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

<script>
function fillCode(code, expiresValue) {
    document.getElementById('code-id').value = code.id;
    document.getElementById('code-value').value = code.code;
    document.getElementById('code-description').value = code.description || '';
    document.getElementById('code-type').value = code.type;
    document.getElementById('code-event').value = code.event_id || '';
    document.getElementById('code-expires').value = expiresValue || '';

    const select = document.getElementById('code-items');
    Array.from(select.options).forEach(opt => {
        opt.selected = (code.items || []).some(item => String(item.id) === opt.value);
    });

    window.scrollTo({top: 0, behavior: 'smooth'});
}
</script>
<?php
$content = ob_get_clean();
$title = 'Item-Codes';
include __DIR__ . '/layout.php';
