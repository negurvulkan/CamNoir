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

function default_theme_settings(): array
{
    return [
        'background' => '#050509',
        'background_accent' => 'rgba(200,162,255,0.1)',
        'card' => '#0c0c12',
        'text' => '#f3f3f7',
        'muted' => '#b3b3c2',
        'primary' => '#c8a2ff',
        'border' => 'rgba(255,255,255,0.06)',
        'link' => '#c8a2ff',
        'button_primary_bg' => '#c8a2ff',
        'button_primary_text' => '#0b0b11',
        'button_secondary_bg' => 'rgba(255,255,255,0.1)',
        'button_secondary_text' => '#f3f3f7',
    ];
}

function sanitize_theme_value(string $value, string $fallback): string
{
    $trimmed = trim($value);
    if ($trimmed === '') {
        return $fallback;
    }
    if (!preg_match('/^[#a-zA-Z0-9(),. %+-]+$/', $trimmed)) {
        return $fallback;
    }
    return $trimmed;
}

function merge_theme_settings(?string $jsonTheme): array
{
    $defaults = default_theme_settings();
    if (!$jsonTheme) {
        return $defaults;
    }
    $data = json_decode($jsonTheme, true);
    if (!is_array($data)) {
        return $defaults;
    }
    foreach ($defaults as $key => $value) {
        if (isset($data[$key]) && is_string($data[$key])) {
            $defaults[$key] = sanitize_theme_value($data[$key], $value);
        }
    }
    return $defaults;
}

function theme_style_block(array $theme): string
{
    $vars = [
        '--bg' => $theme['background'],
        '--bg-accent' => $theme['background_accent'],
        '--card' => $theme['card'],
        '--text' => $theme['text'],
        '--muted' => $theme['muted'],
        '--primary' => $theme['primary'],
        '--link' => $theme['link'],
        '--border' => $theme['border'],
        '--button-primary-bg' => $theme['button_primary_bg'],
        '--button-primary-text' => $theme['button_primary_text'],
        '--button-secondary-bg' => $theme['button_secondary_bg'],
        '--button-secondary-text' => $theme['button_secondary_text'],
    ];

    $lines = array_map(function ($key, $value) {
        return "    {$key}: {$value};";
    }, array_keys($vars), array_values($vars));

    return ":root {\n" . implode("\n", $lines) . "\n}";
}
