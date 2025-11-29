<?php
ob_start();
?>
<style>
body { background: #030308; color: #f5f5f5; }
.slideshow { position: relative; width: 100%; min-height: 70vh; display: flex; align-items: center; justify-content: center; overflow: hidden; border-radius: 18px; border: 1px solid rgba(255,255,255,0.1); background: radial-gradient(circle at 20% 20%, rgba(100,100,150,0.2), transparent 35%), #050509; }
.slideshow img { max-height: 80vh; max-width: 100%; object-fit: contain; border-radius: 12px; box-shadow: 0 20px 60px rgba(0,0,0,0.6); animation: fadeGlitch 2s ease; }
.slideshow .meta { position: absolute; bottom: 12px; left: 12px; padding: 8px 12px; border-radius: 8px; background: rgba(0,0,0,0.55); font-size: 14px; }
@keyframes fadeGlitch { 0% { opacity: 0; transform: scale(0.98); } 20% { opacity: 1; } 60% { filter: hue-rotate(-6deg); } 100% { opacity: 1; transform: scale(1); filter: none; } }
</style>
<h1>Live-Diashow: <?= sanitize_text($event['name']) ?></h1>
<p class="muted">Nur freigegebene Fotos werden angezeigt. Die Ansicht aktualisiert automatisch.</p>
<div class="card slideshow">
    <img id="slideshow-image" src="<?= !empty($photos) ? base_url(str_replace(__DIR__ . '/../public/', '', $photos[0]['file_path'])) : '' ?>" alt="Slideshow Bild">
    <div class="meta" id="slideshow-meta"></div>
</div>
<script>
window.SLIDESHOW = {
    eventId: <?= (int)$event['id'] ?>,
    photos: <?= json_encode($photos) ?>,
    liveUrl: "<?= base_url('api/events/' . (int)$event['id'] . '/live-photos') ?>"
};
</script>
<script src="<?= base_url('js/slideshow.js') ?>"></script>
<?php
$content = ob_get_clean();
$title = 'Live-Diashow – ' . sanitize_text($event['name']);
include __DIR__ . '/layout.php';
