<?php
require_once __DIR__ . '/../bootstrap.php';

$eventRepo = new EventRepository();
$sessionRepo = new SessionRepository();
$photoRepo = new PhotoRepository();

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$base = rtrim(env('BASE_URL', ''), '/');
if ($base !== '' && str_starts_with($uri, $base)) {
    $uri = substr($uri, strlen($base));
}
$uri = '/' . ltrim($uri, '/');

function respond_not_found()
{
    http_response_code(404);
    echo 'Not Found';
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

    // Render frame with delete code
    $deleteCode = strtoupper(substr(random_token(8), 0, 8));
    $text = 'NRW Noir Cam – Delete Code: ' . $deleteCode;
    $margin = 40;
    $frameHeight = $newHeight + $margin;
    $framed = imagecreatetruecolor($newWidth, $frameHeight);
    $bg = imagecolorallocate($framed, 5, 5, 9);
    $fg = imagecolorallocate($framed, 255, 255, 255);
    imagefilledrectangle($framed, 0, 0, $newWidth, $frameHeight, $bg);
    imagecopy($framed, $dst, 0, 0, 0, 0, $newWidth, $newHeight);
    imagerectangle($framed, 0, 0, $newWidth - 1, $frameHeight - 1, $fg);
    $fontSize = 3;
    $textWidth = imagefontwidth($fontSize) * strlen($text);
    $textX = max(4, ($newWidth - $textWidth) / 2);
    imagestring($framed, $fontSize, (int)$textX, $newHeight + 12, $text, $fg);

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
        $deleted = $sessionRepo->deleteByToken($token);
        render('delete_session', ['status' => $deleted ? 'deleted' : 'not_found']);
    } else {
        render('delete_session');
    }
    exit;
}

// Delete photo
if ($uri === '/delete-photo') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $code = preg_replace('/[^a-zA-Z0-9]/', '', $_POST['delete_code'] ?? '');
        $deleted = $photoRepo->deleteByCode($code);
        render('delete_photo', ['status' => $deleted ? 'deleted' : 'not_found']);
    } else {
        render('delete_photo');
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
        $data = [
            'slug' => preg_replace('/[^a-z0-9-]/', '', strtolower($_POST['slug'] ?? '')),
            'name' => trim($_POST['name'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'max_photos_per_session' => (int)($_POST['max_photos_per_session'] ?? 10),
            'auto_delete_days' => (int)($_POST['auto_delete_days'] ?? 30),
            'theme_primary_color' => trim($_POST['theme_primary_color'] ?? '#e0e0e0'),
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

if (preg_match('#^/admin/events/(\d+)/photos$#', $uri, $matches)) {
    require_auth();
    $eventId = (int)$matches[1];
    $event = $eventRepo->find($eventId);
    if (!$event) {
        respond_not_found();
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $code = preg_replace('/[^a-zA-Z0-9]/', '', $_POST['delete_code'] ?? '');
        if ($code) {
            $photoRepo->deleteByCode($code);
        }
        header('Location: ' . base_url('admin/events/' . $eventId . '/photos'));
        exit;
    }
    $photos = $photoRepo->findByEvent($eventId);
    render('admin_photos', ['event' => $event, 'photos' => $photos]);
    exit;
}

respond_not_found();
