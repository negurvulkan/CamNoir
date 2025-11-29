<?php ob_start();
$defaultTheme = default_theme_settings();
$colorFilterExamples = "Monochrom | grayscale(1) contrast(1.05)\nWarm Glow | sepia(0.2) saturate(1.1) brightness(1.05)";
?>
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
        <label class="field">Frame Branding Text
            <input type="text" name="frame_branding_text" placeholder="Night Zero 2026">
        </label>
        <label class="checkbox">
            <input type="checkbox" name="auto_approve_photos" value="1">
            <span>Neue Fotos automatisch freigeben</span>
        </label>
        <label class="field">Event-Banner (URL)
            <input type="url" name="banner_url" placeholder="https://example.com/banner.jpg">
        </label>
        <div class="field" style="grid-column: 1 / -1;">
            <p class="muted small" style="margin: 0 0 6px;">Zusätzliche Farbfilter (optional, ein Filter pro Zeile im Format <code>Name | CSS Filter</code>)</p>
            <textarea name="color_filters" rows="4" placeholder="<?= sanitize_text($colorFilterExamples) ?>"></textarea>
            <p class="muted small" style="margin: 4px 0 0;">Standard-Filter bleiben immer verfügbar. Du kannst hier weitere CSS-Filterdefinitionen hinzufügen (z. B. <code>hue-rotate(12deg) contrast(1.1)</code>).</p>
        </div>
        <div class="field" style="grid-column: 1 / -1;">
            <p class="muted small" style="margin: 0 0 6px;">Theme-Variablen (lassen sich frei per CSS setzen, Standardwerte vorbelegt)</p>
            <div class="grid" style="gap: 10px;">
                <label class="field">Hintergrund
                    <input type="color" name="theme[background]" value="<?= sanitize_text($defaultTheme['background']) ?>">
                </label>
                <label class="field">Akzent Hintergrund
                    <input type="text" name="theme[background_accent]" value="<?= sanitize_text($defaultTheme['background_accent']) ?>" placeholder="rgba(0,0,0,0.1)">
                </label>
                <label class="field">Cards
                    <input type="color" name="theme[card]" value="<?= sanitize_text($defaultTheme['card']) ?>">
                </label>
                <label class="field">Textfarbe
                    <input type="color" name="theme[text]" value="<?= sanitize_text($defaultTheme['text']) ?>">
                </label>
                <label class="field">Muted Text
                    <input type="color" name="theme[muted]" value="<?= sanitize_text($defaultTheme['muted']) ?>">
                </label>
                <label class="field">Primärfarbe
                    <input type="color" name="theme[primary]" value="<?= sanitize_text($defaultTheme['primary']) ?>">
                </label>
                <label class="field">Linkfarbe
                    <input type="color" name="theme[link]" value="<?= sanitize_text($defaultTheme['link']) ?>">
                </label>
                <label class="field">Rahmenfarbe
                    <input type="text" name="theme[border]" value="<?= sanitize_text($defaultTheme['border']) ?>" placeholder="rgba(255,255,255,0.06)">
                </label>
                <label class="field">Button Primär Hintergrund
                    <input type="color" name="theme[button_primary_bg]" value="<?= sanitize_text($defaultTheme['button_primary_bg']) ?>">
                </label>
                <label class="field">Button Primär Text
                    <input type="color" name="theme[button_primary_text]" value="<?= sanitize_text($defaultTheme['button_primary_text']) ?>">
                </label>
                <label class="field">Button Sekundär Hintergrund
                    <input type="text" name="theme[button_secondary_bg]" value="<?= sanitize_text($defaultTheme['button_secondary_bg']) ?>" placeholder="rgba(255,255,255,0.1)">
                </label>
                <label class="field">Button Sekundär Text
                    <input type="color" name="theme[button_secondary_text]" value="<?= sanitize_text($defaultTheme['button_secondary_text']) ?>">
                </label>
            </div>
        </div>
        <button class="primary" type="submit">Speichern</button>
    </form>
</section>

<section class="card">
    <h2>Aktive Events</h2>
    <table>
        <thead>
            <tr><th>Name</th><th>Slug</th><th>Max Fotos</th><th>Auto-Delete</th><th>Auto-Freigabe</th><th>Aktionen</th></tr>
        </thead>
        <tbody>
        <?php foreach ($events as $event): ?>
            <?php $eventData = $event; $eventData['color_filters_lines'] = color_filters_to_lines($event['color_filters'] ?? null); ?>
            <tr>
                <td><?= sanitize_text($event['name']) ?></td>
                <td><code><?= sanitize_text($event['slug']) ?></code></td>
                <td><?= (int)$event['max_photos_per_session'] ?></td>
                <td><?= (int)$event['auto_delete_days'] ?> Tage</td>
                <td><?= (int)$event['auto_approve_photos'] ? 'Ja' : 'Nein' ?></td>
                <td>
                    <a class="secondary" href="<?= base_url('admin/events/' . (int)$event['id']) ?>">Details</a>
                    <a class="secondary" href="<?= base_url('admin/events/' . (int)$event['id'] . '/photos') ?>">Fotos</a>
                    <button class="secondary" onclick='fillEvent(<?= json_encode($eventData) ?>)'>Bearbeiten</button>
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
    document.querySelector('input[name="frame_branding_text"]').value = event.frame_branding_text || '';
    document.querySelector('input[name="auto_approve_photos"]').checked = !!parseInt(event.auto_approve_photos);
    document.querySelector('input[name="banner_url"]').value = event.banner_url || '';
    document.querySelector('textarea[name="color_filters"]').value = event.color_filters_lines || '';
    const defaults = <?= json_encode($defaultTheme) ?>;
    let theme = {...defaults};
    if (event.theme_settings) {
        try {
            const parsed = JSON.parse(event.theme_settings);
            theme = {...defaults, ...parsed};
        } catch (e) {
            theme = {...defaults};
        }
    }
    Object.entries(theme).forEach(([key, value]) => {
        const input = document.querySelector(`input[name="theme[${key}]"]`);
        if (input) {
            input.value = value;
        }
    });
    window.scrollTo({top: 0, behavior: 'smooth'});
}
</script>
<?php
$content = ob_get_clean();
$title = 'Admin Events';
include __DIR__ . '/layout.php';
