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

function default_color_filters(): array
{
    return [
        ['id' => 'none', 'name' => 'Kein Filter', 'css' => 'none'],
        ['id' => 'noir-classic', 'name' => 'Noir Classic', 'css' => 'grayscale(1) contrast(1.12) brightness(0.96)'],
        ['id' => 'noir-punch', 'name' => 'Noir Punch', 'css' => 'grayscale(0.85) contrast(1.24) brightness(0.94) saturate(0.9)'],
        ['id' => 'noir-soft', 'name' => 'Noir Soft', 'css' => 'grayscale(1) contrast(1.05) brightness(1.02) saturate(0.8)'],
        ['id' => 'noir-warm', 'name' => 'Warm Noir', 'css' => 'grayscale(0.8) sepia(0.12) contrast(1.1) brightness(0.98)'],
    ];
}

function sanitize_color_filter_css(string $value): string
{
    $trimmed = trim($value);
    if ($trimmed === '') {
        return '';
    }
    if (!preg_match('/^[a-zA-Z0-9()#.,% \-+]*$/', $trimmed)) {
        return '';
    }
    return $trimmed;
}

function normalize_filter_id(string $name, int $index): string
{
    $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name));
    $slug = trim($slug, '-');
    if ($slug === '') {
        $slug = 'filter-' . $index;
    }
    return $slug;
}

function parse_color_filters_input(?string $raw): array
{
    if (!$raw) {
        return [];
    }
    $lines = preg_split('/\r?\n/', $raw);
    $filters = [];
    $usedIds = [];
    $lineIndex = 0;
    foreach ($lines as $line) {
        $lineIndex++;
        $trimmed = trim($line);
        if ($trimmed === '') {
            continue;
        }
        $name = '';
        $css = '';
        if (strpos($trimmed, '|') !== false) {
            [$name, $css] = array_map('trim', explode('|', $trimmed, 2));
        } elseif (strpos($trimmed, ':') !== false) {
            [$name, $css] = array_map('trim', explode(':', $trimmed, 2));
        } else {
            $name = $trimmed;
        }
        if ($name === '') {
            $name = 'Filter ' . $lineIndex;
        }
        $css = sanitize_color_filter_css($css);
        if ($css === '') {
            continue;
        }
        $id = normalize_filter_id($name, $lineIndex);
        $dedupedId = $id;
        $suffix = 1;
        while (in_array($dedupedId, $usedIds, true)) {
            $suffix++;
            $dedupedId = $id . '-' . $suffix;
        }
        $usedIds[] = $dedupedId;
        $filters[] = [
            'id' => $dedupedId,
            'name' => $name,
            'css' => $css,
        ];
    }
    return $filters;
}

function merge_color_filters(?string $jsonFilters): array
{
    $defaults = default_color_filters();
    $filters = $defaults;
    if ($jsonFilters) {
        $data = json_decode($jsonFilters, true);
        if (is_array($data)) {
            foreach ($data as $index => $filter) {
                if (!isset($filter['name'], $filter['css'])) {
                    continue;
                }
                $id = isset($filter['id']) && is_string($filter['id']) ? normalize_filter_id($filter['id'], $index + 1) : normalize_filter_id($filter['name'], $index + 1);
                $css = sanitize_color_filter_css((string) $filter['css']);
                if ($css === '') {
                    continue;
                }
                $filters[] = [
                    'id' => $id,
                    'name' => (string) $filter['name'],
                    'css' => $css,
                ];
            }
        }
    }

    $unique = [];
    foreach ($filters as $filter) {
        $id = $filter['id'];
        if (isset($unique[$id])) {
            continue;
        }
        $unique[$id] = $filter;
    }

    return array_values($unique);
}

function color_filters_to_lines(?string $jsonFilters): string
{
    $data = json_decode($jsonFilters ?: '[]', true);
    if (!is_array($data) || empty($data)) {
        return '';
    }
    $lines = [];
    foreach ($data as $filter) {
        if (!isset($filter['name'], $filter['css'])) {
            continue;
        }
        $lines[] = trim((string) $filter['name']) . ' | ' . trim((string) $filter['css']);
    }
    return implode("\n", $lines);
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
