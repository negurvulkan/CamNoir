<?php
require_once __DIR__ . '/../bootstrap.php';

$eventRepo = new EventRepository();
$photoRepo = new PhotoRepository();
$events = $eventRepo->findAll();

$totalDeleted = 0;
foreach ($events as $event) {
    $days = (int)$event['auto_delete_days'];
    if ($days <= 0) {
        continue;
    }
    $deleted = $photoRepo->deleteOlderThan((int)$event['id'], $days);
    $totalDeleted += $deleted;
    if ($deleted > 0) {
        echo "Event {$event['slug']}: {$deleted} Fotos gelöscht" . PHP_EOL;
    }
}

echo "Cleanup done. Total: {$totalDeleted}" . PHP_EOL;
