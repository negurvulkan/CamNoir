# NRW Noir Disposable Cam

Minimalistische Web-App, mit der Besucher:innen per QR-Code eine digitale Einwegkamera verwenden können. Implementiert mit PHP/MySQL, Vanilla JS und einem kleinen Admin-Bereich.

## Features
1. Eventbasierte Einwegkamera mit QR-Code-Zugriff und Session-Verwaltung pro Endgerät (inkl. Reset).
2. Foto-Upload mit Löschcode-Watermark und Editor für Farbfilter, Overlays, Sticker, Rahmen & mehrere Fonts.
3. Bonus-Codes pro Event, um zusätzliche Fotos freizuschalten (single use, per session, unlimited, optional Ablauf/Limit).
4. Readonly-Galerie und Live-Diashow je Event; nur freigegebene Fotos, optional öffentlich.
5. Lösch-Workflow: Foto- oder Session-Löschung per Code sowie Löschanträge mit Admin-Prüfung.
6. Admin-Login mit Passwort aus `.env` für geschützten Zugriff.
7. Event-Management im Admin: Anlegen/Bearbeiten/Deaktivieren, Auto-Freigabe, Banner & Frame-Branding.
8. Theming & Filter: konfigurierbare Farbschemata, zusätzliche CSS-Farbfilter und Overlay-Assets pro Event.
9. Admin-Fototools: Freigeben/Sperren, Löschanträge reviewen, Foto-Export (ZIP) und Löschcode-Handling.
10. Bonus-Code-Übersicht im Admin inkl. Nutzungshistorie pro Session.
11. Event-Dashboards mit Statistiken, Delete-Log und QR-Code-Generator.
12. Konfigurierbare Auto-Löschung alter Fotos per Cron-Job.
13. Platzhalterseite für Datenschutzerklärung & keine Speicherung personenbezogener Daten.
14. Lokale Bildanpassungen (Helligkeit/Kontrast) vor Filtern, Stickern & Rahmen.

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

## Bildanpassung im Editor
- Neuer Tab „Bild“ mit Reglern für Helligkeit und Kontrast.
- Anpassungen wirken direkt auf die Pixel des Basisfotos (Canvas) und werden vor CSS-Farbfiltern, PNG-Overlays, Stickern, Text und Rahmen angewendet.
- Ein Reset-Button stellt die Standardwerte wieder her, wenn die Regler nicht genutzt werden sollen.

## Sicherheit & Datenschutz
- Keine personenbezogenen Daten werden gespeichert.
- Löschcodes werden im Bild gerendert, damit abgebildete Personen selbst löschen können.
- Admin-Bereich ist passwortgeschützt; die Event-Galerie ist rein lesend und verzichtet auf Nutzerkonten.
