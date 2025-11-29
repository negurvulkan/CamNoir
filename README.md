# NRW Noir Disposable Cam

Minimalistische Web-App, mit der Besucher:innen per QR-Code eine digitale Einwegkamera verwenden können. Implementiert mit PHP/MySQL, Vanilla JS und einem kleinen Admin-Bereich.

## Features
1. Eventbasierte Einwegkamera mit QR-Code-Zugriff für Gäste.
2. Session-Verwaltung pro Endgerät inklusive optionaler Session-Löschung.
3. Foto-Upload mit Löschcode, der ins Bild gerendert wird.
4. Einfügen von Text (verschiedene Fonts), Stickern und Rahmen.
5. Readonly-Galerie pro Event (optional öffentlich erreichbar).
6. Einzel- und Mehrfachlöschung über Löschcode oder Session-Reset.
7. Admin-Login mit Passwort aus `.env` für geschützten Zugriff.
8. Admin-Oberfläche zum Anlegen, Bearbeiten und Deaktivieren von Events.
9. Admin-Ansicht zum Durchstöbern und Löschen von Event-Fotos.
10. Konfigurierbare Auto-Löschung alter Fotos per Cron-Job.
11. Platzhalterseite für Datenschutzerklärung.
12. Keine Speicherung personenbezogener Daten; reine Gastnutzung ohne Konten.

## Setup
1. Kopiere `.env.example` zu `.env` und passe Datenbank & Admin-Passwort an.
2. Importiere `sql_schema.sql` in eine MySQL/MariaDB-Datenbank.
3. Richte den Webroot auf `public/` aus. Wenn die App in einem Unterordner läuft, setze `BASE_URL` entsprechend (z. B. `/cam`).
4. Stelle sicher, dass PHP-GD aktiviert ist (für Bildverarbeitung) und der Upload-Ordner (`UPLOAD_DIR`, Standard `public/uploads`) beschreibbar ist.

## Routen
- `/e/{slug}`: Event-Frontend mit Kamera, Session-Verwaltung und Upload.
- `/e/{slug}/upload` (POST): Upload-Endpoint für Bilder.
- `/e/{slug}/gallery`: Readonly-Galerie eines Events.
- `/delete-session`: Formular, um alle Fotos einer Session zu löschen.
- `/delete-photo`: Formular, um ein einzelnes Foto per Löschcode zu löschen.
- `/privacy`: Platzhalter für Datenschutzerklärung.
- `/admin/login`: Admin-Login mit Passwort aus `.env`.
- `/admin/events`: Events anlegen/bearbeiten.
- `/admin/events/{id}/photos`: Fotos eines Events einsehen und per Löschcode löschen.

## Auto-Cleanup
`scripts/cleanup.php` löscht Fotos, die älter als die Event-Konfiguration `auto_delete_days` sind. Per Cron z. B. täglich ausführen.

## Sicherheit & Datenschutz
- Keine personenbezogenen Daten werden gespeichert.
- Löschcodes werden im Bild gerendert, damit abgebildete Personen selbst löschen können.
- Admin-Bereich ist passwortgeschützt; die Event-Galerie ist rein lesend und verzichtet auf Nutzerkonten.
