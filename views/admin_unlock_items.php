<?php ob_start(); ?>
<div class="flex-between">
    <h1>Items verwalten</h1>
    <div class="flex" style="gap: 10px;">
        <a class="secondary" href="<?= base_url('admin/unlock-codes') ?>">Codes</a>
        <a class="secondary" href="<?= base_url('admin/events') ?>">Events</a>
    </div>
</div>

<section class="card">
    <h2>Item anlegen/aktualisieren</h2>
    <form method="POST" class="grid">
        <input type="hidden" name="id" id="item-id" value="">
        <label class="field">Name
            <input type="text" name="name" id="item-name" required placeholder="Sticker: Stern">
        </label>
        <label class="field">Typ
            <select name="type" id="item-type">
                <option value="sticker">Sticker</option>
                <option value="overlay">Overlay</option>
                <option value="filter">Filter (CSS)</option>
                <option value="frame">Frame</option>
            </select>
        </label>
        <label class="field">Seltenheit (optional)
            <select name="rarity" id="item-rarity">
                <option value="">–</option>
                <option value="common">Common</option>
                <option value="uncommon">Uncommon</option>
                <option value="rare">Rare</option>
                <option value="legendary">Legendary</option>
            </select>
        </label>
        <label class="field">Event (leer = global)
            <select name="event_id" id="item-event">
                <option value="">Global</option>
                <?php foreach ($events as $event): ?>
                    <option value="<?= (int)$event['id'] ?>"><?= sanitize_text($event['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="field">Asset-Pfad (optional)
            <input type="text" name="asset_path" id="item-asset" placeholder="/uploads/sticker.png">
        </label>
        <label class="field">CSS Filter (optional)
            <input type="text" name="css_filter" id="item-css" placeholder="grayscale(1) contrast(1.1)">
        </label>
        <button class="primary" type="submit">Speichern</button>
    </form>
</section>

<section class="card">
    <h2>Vorhandene Items</h2>
    <div class="table-wrapper">
        <table>
            <thead>
            <tr><th>Name</th><th>Typ</th><th>Rarity</th><th>Event</th><th>Asset</th><th>CSS</th><th>Aktionen</th></tr>
            </thead>
            <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td><?= sanitize_text($item['name']) ?></td>
                    <td><?= sanitize_text($item['type']) ?></td>
                    <td><?= $item['rarity'] ? sanitize_text($item['rarity']) : '–' ?></td>
                    <td><?= $item['event_id'] ? sanitize_text($item['event_name'] ?? ('#' . $item['event_id'])) : 'Global' ?></td>
                    <td><?= $item['asset_path'] ? sanitize_text($item['asset_path']) : '–' ?></td>
                    <td><?= $item['css_filter'] ? sanitize_text($item['css_filter']) : '–' ?></td>
                    <td class="actions">
                        <button class="secondary" onclick='fillItem(<?= json_encode($item) ?>)'>Bearbeiten</button>
                        <form method="POST" style="display:inline" onsubmit="return confirm('Item wirklich löschen?');">
                            <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
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
function fillItem(item) {
    document.getElementById('item-id').value = item.id;
    document.getElementById('item-name').value = item.name;
    document.getElementById('item-type').value = item.type;
    document.getElementById('item-rarity').value = item.rarity || '';
    document.getElementById('item-event').value = item.event_id || '';
    document.getElementById('item-asset').value = item.asset_path || '';
    document.getElementById('item-css').value = item.css_filter || '';
    window.scrollTo({top: 0, behavior: 'smooth'});
}
</script>
<?php
$content = ob_get_clean();
$title = 'Items verwalten';
include __DIR__ . '/layout.php';
