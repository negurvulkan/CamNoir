<?php

function base_url(string $path = ''): string
{
    $base = rtrim(env('BASE_URL', ''), '/');
    return $base . '/' . ltrim($path, '/');
}

function random_token(int $length = 24): string
{
    return substr(bin2hex(random_bytes($length)), 0, $length);
}

function uuid(): string
{
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function render(string $view, array $data = []): void
{
    extract($data);
    include __DIR__ . '/../views/' . $view . '.php';
}

function ensure_upload_dir(): string
{
    $dir = env('UPLOAD_DIR', __DIR__ . '/../public/uploads');
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    return rtrim($dir, '/');
}

function is_authenticated(): bool
{
    session_start();
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

function require_auth(): void
{
    if (!is_authenticated()) {
        header('Location: ' . base_url('admin/login'));
        exit;
    }
}

function sanitize_text(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
