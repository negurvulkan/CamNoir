<?php
require_once __DIR__ . '/../bootstrap.php';

$eventRepo = new EventRepository();
$sessionRepo = new SessionRepository();
$photoRepo = new PhotoRepository();
$deleteLogRepo = new DeleteLogRepository();

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$base = rtrim(env('BASE_URL', ''), '/');
if ($base !== '' && str_starts_with($uri, $base)) {
    $uri = substr($uri, strlen($base));
}
$uri = '/' . trim($uri, '/');

function respond_not_found()
{
    http_response_code(404);
    echo 'Not Found';
    exit;
}

function respond_json(array $data): void
{
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

if ($uri === '/' || $uri === '') {
    header('Location: ' . base_url('privacy'));
    exit;
}

// Event routes
if (preg_match('#^/e/([a-zA-Z0-9-]+)$#', $uri, $matches) && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $slug = $matches[1];
    $event = $eventRepo->findBySlug($slug);
    if (!$event) {
        respond_not_found();
    }
    $cookieName = 'cam_session_' . $slug;
    $token = $_COOKIE[$cookieName] ?? null;
    if (!$token || !$sessionRepo->findByToken($token)) {
        $token = random_token(24);
        $sessionId = $sessionRepo->create((int)$event['id'], $token);
        setcookie($cookieName, $token, time() + 60 * 60 * 24 * 30, '/');
        $session = ['id' => $sessionId, 'session_token' => $token, 'photo_count' => 0];
    } else {
        $session = $sessionRepo->findByToken($token);
    }
    render('event', [
        'event' => $event,
        'session' => $session,
    ]);
    exit;
}

// API routes
if ($uri === '/api/events' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $events = array_map(function ($event) {
        return [
            'id' => (int) $event['id'],
            'name' => $event['name'],
            'slug' => $event['slug'],
            'description' => $event['description'],
            'frame_branding_text' => $event['frame_branding_text'],
            'created_at' => $event['created_at'],
        ];
    }, $eventRepo->findAll());
    respond_json(['events' => $events]);
}

if (preg_match('#^/api/events/(\d+)/photos$#', $uri, $matches) && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $eventId = (int) $matches[1];
    $event = $eventRepo->find($eventId);
    if (!$event) {
        respond_not_found();
    }
    $photos = $photoRepo->findPublicByEvent($eventId);
    respond_json(['event' => $eventId, 'photos' => $photos]);
}

if (preg_match('#^/api/photos/([a-f0-9-]+)$#', $uri, $matches) && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $uuid = $matches[1];
    $photo = $photoRepo->findByUuid($uuid);
    if (!$photo || $photo['deleted_at'] !== null || !(int)$photo['is_approved'] || $photo['delete_request_status'] === 'pending') {
        respond_not_found();
    }
    respond_json($photo);
}

if (preg_match('#^/api/events/(\d+)/live-photos$#', $uri, $matches) && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $eventId = (int) $matches[1];
    $event = $eventRepo->find($eventId);
    if (!$event) {
        respond_not_found();
    }
    $since = $_GET['since'] ?? null;
    $photos = $photoRepo->findApprovedSince($eventId, $since);
    respond_json(['event' => $eventId, 'photos' => $photos]);
}

if ($uri === '/api/delete-requests' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = preg_replace('/[^a-zA-Z0-9]/', '', $_POST['delete_code'] ?? '');
    $reason = trim($_POST['reason'] ?? '');
    $note = trim($_POST['note'] ?? '');
    $reason = substr($reason, 0, 255);
    $note = substr($note, 0, 2000);
    $photo = $photoRepo->requestDeletionByCode($code, $reason, $note);
    if (!$photo) {
        respond_json(['success' => false, 'error' => 'not_found']);
    }
    respond_json([
        'success' => true,
        'status' => $photo['delete_request_status'],
    ]);
}

if (preg_match('#^/e/([a-zA-Z0-9-]+)/gallery$#', $uri, $matches) && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $slug = $matches[1];
    $event = $eventRepo->findBySlug($slug);
    if (!$event) {
        respond_not_found();
    }
    $photos = $photoRepo->findPublicByEvent((int)$event['id']);
    render('gallery', [
        'event' => $event,
        'photos' => $photos,
    ]);
    exit;
}

if (preg_match('#^/e/([a-zA-Z0-9-]+)/slideshow$#', $uri, $matches) && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $slug = $matches[1];
    $event = $eventRepo->findBySlug($slug);
    if (!$event) {
        respond_not_found();
    }
    $photos = $photoRepo->findPublicByEvent((int)$event['id']);
    render('slideshow', [
        'event' => $event,
        'photos' => $photos,
    ]);
    exit;
}

if (preg_match('#^/e/([a-zA-Z0-9-]+)/upload$#', $uri, $matches) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $slug = $matches[1];
    $event = $eventRepo->findBySlug($slug);
    if (!$event) {
        respond_not_found();
    }
    $cookieName = 'cam_session_' . $slug;
    $token = $_COOKIE[$cookieName] ?? ($_POST['session_token'] ?? '');
    if (!$token) {
        http_response_code(400);
        echo json_encode(['error' => 'Session missing']);
        exit;
    }
    $session = $sessionRepo->findByToken($token);
    if (!$session || (int)$session['event_id'] !== (int)$event['id']) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid session']);
        exit;
    }
    if ($session['photo_count'] >= $event['max_photos_per_session']) {
        http_response_code(400);
        echo json_encode(['error' => 'Limit reached']);
        exit;
    }
    if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['error' => 'Upload failed']);
        exit;
    }
    $tmp = $_FILES['photo']['tmp_name'];
    $imageInfo = getimagesize($tmp);
    if ($imageInfo === false) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid image']);
        exit;
    }
    [$width, $height] = $imageInfo;
    $maxSize = 2000;
    $ratio = min($maxSize / $width, $maxSize / $height, 1);
    $newWidth = (int)($width * $ratio);
    $newHeight = (int)($height * $ratio);
    $src = imagecreatefromstring(file_get_contents($tmp));
    $dst = imagecreatetruecolor($newWidth, $newHeight);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

    // Render frame with delete code and branding
    $deleteCode = strtoupper(substr(random_token(8), 0, 8));
    $frameLabel = trim(($event['frame_branding_text'] ?? '') ?: ($event['name'] . ' – NRW Noir Cam'));
    $text = $frameLabel . ' – Delete Code: ' . $deleteCode;
    $margin = 48;
    $frameHeight = $newHeight + $margin;
    $framed = imagecreatetruecolor($newWidth, $frameHeight);
    $bg = imagecolorallocate($framed, 5, 5, 9);
    [$r, $g, $b] = sscanf('#c8a2ff', '#%02x%02x%02x');
    $fg = imagecolorallocate($framed, $r, $g, $b);
    imagefilledrectangle($framed, 0, 0, $newWidth, $frameHeight, $bg);
    imagecopy($framed, $dst, 0, 0, 0, 0, $newWidth, $newHeight);
    imagerectangle($framed, 0, 0, $newWidth - 1, $frameHeight - 1, $fg);
    $fontSize = 3;
    $textWidth = imagefontwidth($fontSize) * strlen($text);
    $textX = max(4, ($newWidth - $textWidth) / 2);
    imagestring($framed, $fontSize, (int)$textX, $newHeight + 14, $text, $fg);

    $uploadDir = ensure_upload_dir();
    $pictureUuid = uuid();
    $filePath = $uploadDir . '/' . $pictureUuid . '.jpg';
    imagejpeg($framed, $filePath, 85);
    imagedestroy($src);
    imagedestroy($dst);
    imagedestroy($framed);

    $photoRepo->create([
        'event_id' => (int)$event['id'],
        'session_id' => (int)$session['id'],
        'picture_uuid' => $pictureUuid,
        'delete_code' => $deleteCode,
        'file_path' => $filePath,
        'is_approved' => (int) ($event['auto_approve_photos'] ?? 0),
    ]);
    $sessionRepo->incrementPhotoCount((int)$session['id']);

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'delete_code' => $deleteCode]);
    exit;
}

// Delete session
if ($uri === '/delete-session') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = preg_replace('/[^a-zA-Z0-9]/', '', $_POST['session_token'] ?? '');
        $result = $sessionRepo->deleteByToken($token);
        if ($result['deleted'] > 0 && $result['event_id']) {
            $deleteLogRepo->log((int)$result['event_id'], 'session', $token);
        }
        render('delete_session', ['status' => $result['deleted'] ? 'deleted' : 'not_found']);
    } else {
        render('delete_session');
    }
    exit;
}

// Delete photo
if ($uri === '/delete-photo') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $code = preg_replace('/[^a-zA-Z0-9]/', '', $_POST['delete_code'] ?? '');
        $reason = trim($_POST['reason'] ?? '');
        $note = trim($_POST['note'] ?? '');
        $reason = substr($reason, 0, 255);
        $note = substr($note, 0, 2000);
        $photo = $photoRepo->requestDeletionByCode($code, $reason, $note);
        render('delete_photo', [
            'status' => $photo ? $photo['delete_request_status'] : 'not_found',
            'prefill_code' => $code,
        ]);
    } else {
        $prefillCode = preg_replace('/[^a-zA-Z0-9]/', '', $_GET['delete_code'] ?? '');
        render('delete_photo', ['prefill_code' => $prefillCode]);
    }
    exit;
}

if ($uri === '/privacy') {
    render('privacy');
    exit;
}

// Admin routes
if ($uri === '/admin/login') {
    session_start();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $password = $_POST['password'] ?? '';
        if ($password === env('ADMIN_PASSWORD')) {
            $_SESSION['admin_logged_in'] = true;
            header('Location: ' . base_url('admin/events'));
            exit;
        }
        render('admin_login', ['error' => 'Falsches Passwort.']);
    } else {
        render('admin_login');
    }
    exit;
}

if ($uri === '/admin/logout') {
    session_start();
    session_destroy();
    header('Location: ' . base_url('admin/login'));
    exit;
}

if ($uri === '/admin/events') {
    require_auth();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $themeInput = $_POST['theme'] ?? [];
        $theme = default_theme_settings();
        foreach ($theme as $key => $value) {
            if (isset($themeInput[$key]) && is_string($themeInput[$key])) {
                $theme[$key] = sanitize_theme_value($themeInput[$key], $value);
            }
        }
        $data = [
            'slug' => preg_replace('/[^a-z0-9-]/', '', strtolower($_POST['slug'] ?? '')),
            'name' => trim($_POST['name'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'max_photos_per_session' => (int)($_POST['max_photos_per_session'] ?? 10),
            'auto_delete_days' => (int)($_POST['auto_delete_days'] ?? 30),
            'frame_branding_text' => trim($_POST['frame_branding_text'] ?? ''),
            'auto_approve_photos' => isset($_POST['auto_approve_photos']) ? 1 : 0,
            'theme_settings' => json_encode($theme, JSON_UNESCAPED_SLASHES),
            'banner_url' => trim($_POST['banner_url'] ?? ''),
        ];
        if (!empty($_POST['id'])) {
            $eventRepo->update((int)$_POST['id'], $data);
        } else {
            $eventRepo->create($data);
        }
        header('Location: ' . base_url('admin/events'));
        exit;
    }
    $events = $eventRepo->findAll();
    render('admin_events', ['events' => $events]);
    exit;
}

if (preg_match('#^/admin/events/(\d+)$#', $uri, $matches)) {
    require_auth();
    $eventId = (int) $matches[1];
    $event = $eventRepo->find($eventId);
    if (!$event) {
        respond_not_found();
    }
    $stats = [
        'sessions' => $sessionRepo->countByEvent($eventId),
        'photos' => $photoRepo->countByEvent($eventId),
        'last_upload' => $photoRepo->latestUploadAt($eventId),
        'avg_per_session' => $photoRepo->averagePerSession($eventId),
    ];
    $sessions = $sessionRepo->findByEvent($eventId);
    $deleteStats = $deleteLogRepo->countsForEvent($eventId);
    $deleteRows = $deleteLogRepo->latestForEvent($eventId, 12);
    render('admin_event_detail', ['event' => $event, 'stats' => $stats, 'sessions' => $sessions, 'delete_stats' => $deleteStats, 'delete_rows' => $deleteRows]);
    exit;
}

if (preg_match('#^/admin/events/(\d+)/photos$#', $uri, $matches)) {
    require_auth();
    $eventId = (int)$matches[1];
    $event = $eventRepo->find($eventId);
    if (!$event) {
        respond_not_found();
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        if ($action === 'delete') {
            $code = preg_replace('/[^a-zA-Z0-9]/', '', $_POST['delete_code'] ?? '');
            if ($code) {
                $photo = $photoRepo->deleteByCode($code);
                if ($photo) {
                    $deleteLogRepo->log((int)$photo['event_id'], 'delete_code', $code);
                }
            }
            header('Location: ' . base_url('admin/events/' . $eventId . '/photos'));
            exit;
        }
        if ($action === 'approve') {
            $photoId = (int)($_POST['photo_id'] ?? 0);
            $state = isset($_POST['state']) && $_POST['state'] === '1';
            if ($photoId > 0) {
                $photoRepo->setApproval($photoId, $state);
            }
            header('Location: ' . base_url('admin/events/' . $eventId . '/photos'));
            exit;
        }
        if ($action === 'review_delete') {
            $photoId = (int)($_POST['photo_id'] ?? 0);
            $decision = $_POST['decision'] ?? '';
            if ($photoId > 0) {
                if ($decision === 'approve') {
                    $photo = $photoRepo->approveDeleteRequest($photoId);
                    if ($photo) {
                        $deleteLogRepo->log((int)$photo['event_id'], 'delete_code', $photo['delete_code'] ?? null);
                    }
                } elseif ($decision === 'reject') {
                    $photoRepo->rejectDeleteRequest($photoId);
                }
            }
            header('Location: ' . base_url('admin/events/' . $eventId . '/photos'));
            exit;
        }
        if ($action === 'reset_delete_request') {
            $photoId = (int)($_POST['photo_id'] ?? 0);
            if ($photoId > 0) {
                $photoRepo->resetDeleteRequest($photoId);
            }
            header('Location: ' . base_url('admin/events/' . $eventId . '/photos'));
            exit;
        }
        if ($action === 'export') {
            $selected = array_map('intval', $_POST['photo_ids'] ?? []);
            $photos = $selected ? $photoRepo->findByIdsForEvent($eventId, $selected) : $photoRepo->findByEvent($eventId);
            if (empty($photos)) {
                header('Location: ' . base_url('admin/events/' . $eventId . '/photos'));
                exit;
            }
            $zip = new ZipArchive();
            $tmp = tempnam(sys_get_temp_dir(), 'photos_') . '.zip';
            $zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE);
            foreach ($photos as $photo) {
                if (is_file($photo['file_path'])) {
                    $zip->addFile($photo['file_path'], basename($photo['file_path']));
                }
            }
            $zip->close();
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="event-' . $eventId . '-photos.zip"');
            header('Content-Length: ' . filesize($tmp));
            readfile($tmp);
            unlink($tmp);
            exit;
        }
    }
    $photos = $photoRepo->findByEvent($eventId);
    render('admin_photos', ['event' => $event, 'photos' => $photos]);
    exit;
}

respond_not_found();
