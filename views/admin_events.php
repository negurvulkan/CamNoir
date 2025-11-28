<?php ob_start(); ?>
<div class="flex-between">
    <h1>Events</h1>
    <a href="<?= base_url('admin/logout') ?>" class="secondary">Logout</a>
</div>
<section class="card">
    <h2>Event anlegen/aktualisieren</h2>
    <form method="POST" class="grid">
        <input type="hidden" name="id" id="event-id" value="">
        <label class="field">Name
            <input type="text" name="name" required>
        </label>
        <label class="field">Slug
            <input type="text" name="slug" required>
        </label>
        <label class="field">Beschreibung
            <textarea name="description" rows="2"></textarea>
        </label>
        <label class="field">Max Fotos pro Session
            <input type="number" name="max_photos_per_session" min="1" max="50" value="10">
        </label>
        <label class="field">Auto-Delete (Tage)
            <input type="number" name="auto_delete_days" min="1" max="365" value="30">
        </label>
        <label class="field">Primärfarbe
            <input type="text" name="theme_primary_color" value="#e0e0e0">
        </label>
        <button class="primary" type="submit">Speichern</button>
    </form>
</section>

<section class="card">
    <h2>Aktive Events</h2>
    <table>
        <thead>
            <tr><th>Name</th><th>Slug</th><th>Max Fotos</th><th>Auto-Delete</th><th>Aktionen</th></tr>
        </thead>
        <tbody>
        <?php foreach ($events as $event): ?>
            <tr>
                <td><?= sanitize_text($event['name']) ?></td>
                <td><code><?= sanitize_text($event['slug']) ?></code></td>
                <td><?= (int)$event['max_photos_per_session'] ?></td>
                <td><?= (int)$event['auto_delete_days'] ?> Tage</td>
                <td>
                    <a class="secondary" href="<?= base_url('admin/events/' . (int)$event['id'] . '/photos') ?>">Fotos</a>
                    <button class="secondary" onclick='fillEvent(<?= json_encode($event) ?>)'>Bearbeiten</button>
                    <a class="secondary" href="<?= base_url('e/' . sanitize_text($event['slug'])) ?>" target="_blank">Event-Link</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>

<script>
function fillEvent(event) {
    document.getElementById('event-id').value = event.id;
    document.querySelector('input[name="name"]').value = event.name;
    document.querySelector('input[name="slug"]').value = event.slug;
    document.querySelector('textarea[name="description"]').value = event.description || '';
    document.querySelector('input[name="max_photos_per_session"]').value = event.max_photos_per_session;
    document.querySelector('input[name="auto_delete_days"]').value = event.auto_delete_days;
    document.querySelector('input[name="theme_primary_color"]').value = event.theme_primary_color || '#e0e0e0';
    window.scrollTo({top: 0, behavior: 'smooth'});
}
</script>
<?php
$content = ob_get_clean();
$title = 'Admin Events';
include __DIR__ . '/layout.php';
