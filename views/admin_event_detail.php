<?php ob_start();
$theme = merge_theme_settings($event['theme_settings'] ?? null);
$colorFilters = merge_color_filters($event['color_filters'] ?? null);
?>
<div class="flex-between">
    <h1>Event-Details</h1>
    <a class="secondary" href="<?= base_url('admin/events') ?>">Zurück</a>
</div>
<div class="actions" style="margin-top: -8px;">
    <a class="secondary" href="<?= base_url('admin/events/' . (int)$event['id'] . '/bonus-codes') ?>">Bonus-Codes</a>
    <a class="secondary" href="<?= base_url('admin/events/' . (int)$event['id'] . '/photos') ?>">Fotos verwalten</a>
</div>
<section class="card grid">
    <div>
        <p class="muted small">Name</p>
        <h3><?= sanitize_text($event['name']) ?></h3>
        <p class="muted">Slug: <code><?= sanitize_text($event['slug']) ?></code></p>
        <p class="muted">Frame-Branding: <?= $event['frame_branding_text'] ? sanitize_text($event['frame_branding_text']) : '–' ?></p>
        <p class="muted">Auto-Freigabe: <?= (int)$event['auto_approve_photos'] ? 'Aktiv' : 'Deaktiviert' ?></p>
        <p class="muted">Banner: <?= $event['banner_url'] ? '<a href="' . sanitize_text($event['banner_url']) . '" target="_blank">gepflegt</a>' : '–' ?></p>
    </div>
    <div>
        <p class="muted small">Aktive Sessions</p>
        <h2><?= (int)$stats['sessions'] ?></h2>
    </div>
    <div>
        <p class="muted small">Fotos</p>
        <h2><?= (int)$stats['photos'] ?></h2>
    </div>
    <div>
        <p class="muted small">Durchschnitt Fotos/Session</p>
        <h2><?= number_format($stats['avg_per_session'], 2) ?></h2>
    </div>
    <div>
        <p class="muted small">Letzter Upload</p>
        <h2><?= $stats['last_upload'] ? sanitize_text($stats['last_upload']) : '–' ?></h2>
    </div>
    <div>
        <p class="muted small">Löschungen</p>
        <h2><?= (int)$delete_stats['delete_code'] ?> Codes / <?= (int)$delete_stats['session'] ?> Sessions</h2>
    </div>
</section>

<section class="card">
    <h2>Farbfilter</h2>
    <div class="table-wrapper">
        <table>
            <thead><tr><th>Name</th><th>CSS Filter</th></tr></thead>
            <tbody>
                <?php foreach ($colorFilters as $filter): ?>
                    <tr>
                        <td><?= sanitize_text($filter['name']) ?></td>
                        <td><code><?= sanitize_text($filter['css']) ?></code></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="card">
    <h2>Theme-Konfiguration</h2>
    <div class="grid" style="gap: 8px;">
        <?php foreach ($theme as $key => $value): ?>
            <div>
                <p class="muted small"><?= sanitize_text($key) ?></p>
                <code><?= sanitize_text($value) ?></code>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<section class="card">
    <h2>Delete-Log</h2>
    <div class="table-wrapper">
        <table>
            <thead><tr><th>Typ</th><th>Detail</th><th>Zeitpunkt</th></tr></thead>
            <tbody>
                <?php foreach ($delete_rows as $row): ?>
                    <tr>
                        <td><?= sanitize_text($row['type']) ?></td>
                        <td><?= $row['detail'] ? sanitize_text($row['detail']) : '–' ?></td>
                        <td><?= sanitize_text($row['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="card">
    <h2>QR-Code generieren</h2>
    <p class="muted small">Direktlink: <code><?= base_url('e/' . sanitize_text($event['slug'])) ?></code></p>
    <div class="actions">
        <button class="primary" id="qr-generate">QR-Code generieren</button>
        <button class="secondary" id="qr-download-png" disabled>PNG speichern</button>
        <button class="secondary" id="qr-download-svg" disabled>SVG speichern</button>
    </div>
    <label class="checkbox" style="margin-top:8px;">
        <input type="checkbox" id="qr-branding" checked>
        <span>Branding (NRW Noir) einblenden</span>
    </label>
    <div id="qr-preview" class="qr-preview"></div>
</section>

<section class="card">
    <h2>Sessions</h2>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr><th>Session</th><th>Fotos</th><th>Bonus</th><th>Limit</th><th>Letzte Aktivität</th></tr>
            </thead>
            <tbody>
            <?php foreach ($sessions as $session): ?>
                <tr>
                    <td><code><?= sanitize_text($session['session_token']) ?></code></td>
                    <td><?= (int)$session['photo_count'] ?></td>
                    <td><?= (int)($session['extra_photos'] ?? 0) ?></td>
                    <td><?= (int)$event['max_photos_per_session'] + (int)($session['extra_photos'] ?? 0) ?></td>
                    <td><?= sanitize_text($session['last_activity_at']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<script src="<?= base_url('js/qrcode.min.js') ?>"></script>
<script>
const qrContainer = document.getElementById('qr-preview');
const link = "<?= base_url('e/' . sanitize_text($event['slug'])) ?>";
const branding = document.getElementById('qr-branding');
let currentSvg = '';
let currentPng = null;

function renderQr() {
    qrContainer.innerHTML = '';
    const options = { width: 240, color: { dark: '#050509', light: '#ffffff' } };
    if (branding.checked) {
        currentSvg = '<' + '?xml version="1.0" encoding="UTF-8"?>';
    }
    QRCode.toString(link, {type: 'svg', margin: 2, color: {dark: '#050509', light: '#ffffff'}, width: 240}, function(err, svg) {
        if (err) return;
        let finalSvg = svg;
        if (branding.checked) {
            const brand = `<text x="50%" y="95%" text-anchor="middle" font-size="12" fill="#050509" font-family="Inter, Arial, sans-serif">NRW Noir – <?= sanitize_text($event['name']) ?></text>`;
            finalSvg = svg.replace('</svg>', brand + '</svg>');
        }
        currentSvg = finalSvg;
        qrContainer.innerHTML = finalSvg;
        document.getElementById('qr-download-svg').disabled = false;
    });
    QRCode.toCanvas(link, options, function(err, canvas) {
        if (err) return;
        if (branding.checked) {
            const ctx = canvas.getContext('2d');
            ctx.fillStyle = 'rgba(0,0,0,0.8)';
            ctx.fillRect(0, canvas.height - 28, canvas.width, 28);
            ctx.fillStyle = '#fff';
            ctx.font = '12px Inter, Arial, sans-serif';
            ctx.textAlign = 'center';
            ctx.fillText('NRW Noir – <?= sanitize_text($event['name']) ?>', canvas.width / 2, canvas.height - 10);
        }
        currentPng = canvas.toDataURL('image/png');
        document.getElementById('qr-download-png').disabled = false;
    });
}

document.getElementById('qr-generate').addEventListener('click', renderQr);
document.getElementById('qr-branding').addEventListener('change', renderQr);

document.getElementById('qr-download-png').addEventListener('click', () => {
    if (!currentPng) return;
    const a = document.createElement('a');
    a.href = currentPng;
    a.download = 'event-qr.png';
    a.click();
});

document.getElementById('qr-download-svg').addEventListener('click', () => {
    if (!currentSvg) return;
    const blob = new Blob([currentSvg], {type: 'image/svg+xml'});
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'event-qr.svg';
    a.click();
    URL.revokeObjectURL(url);
});
</script>
<?php
$content = ob_get_clean();
$title = 'Admin Event-Details';
include __DIR__ . '/layout.php';
