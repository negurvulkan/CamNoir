<?php ob_start(); ?>
<h1>Datenschutzerklärung (Platzhalter)</h1>
<p class="muted">Hier kann ein Link zu einer vollständigen Datenschutzerklärung des Veranstalters eingefügt werden.</p>
<p class="muted">Keine Logins, keine Tracking-Skripte, keine personenbezogenen Daten. Löschcodes sind im Bild sichtbar, Sessions können jederzeit gelöscht werden.</p>
<?php
$content = ob_get_clean();
$title = 'Datenschutz';
include __DIR__ . '/layout.php';
