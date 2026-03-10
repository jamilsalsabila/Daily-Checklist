<?php

declare(strict_types=1);

function app_base_url(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? null) === '443');
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');

    return $scheme . '://' . $host . ($dir === '' ? '' : $dir);
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function storage_path(string $path = ''): string
{
    $base = __DIR__ . '/../storage';
    ensure_writable_directory($base);

    return $path === '' ? $base : $base . '/' . ltrim($path, '/');
}

function ensure_writable_directory(string $directory): void
{
    if (!is_dir($directory)) {
        mkdir($directory, 0777, true);
    }

    @chmod($directory, 0777);
}

function ensure_writable_file(string $filePath): void
{
    $directory = dirname($filePath);
    ensure_writable_directory($directory);

    if (!file_exists($filePath)) {
        touch($filePath);
    }

    @chmod($filePath, 0666);
}

function normalize_pdf_text(string $value): string
{
    $value = str_replace(
        ["\r\n", "\r", '°', '≤', '☐', '✔', '✘', '—', '–', '’', '“', '”', '⸻'],
        ["\n", "\n", ' deg', '<=', '[ ]', '[v]', '[x]', '-', '-', "'", '"', '"', '---'],
        $value
    );

    $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $value);
    return $converted === false ? preg_replace('/[^\x20-\x7E\n]/', '', $value) ?? '' : $converted;
}

function pdf_file_path(string $code): string
{
    $directory = storage_path('pdf');
    ensure_writable_directory($directory);

    return $directory . '/' . $code . '.pdf';
}

function render_text_style_attr(mixed $style): string
{
    if (!is_array($style)) {
        return '';
    }

    $fontMap = [
        'default' => 'inherit',
        'sans' => '"Avenir Next", Avenir, "Segoe UI", sans-serif',
        'serif' => '"Iowan Old Style", Georgia, serif',
        'mono' => '"Courier New", ui-monospace, monospace',
    ];

    $css = [];
    $fontFamily = (string) ($style['font_family'] ?? '');
    if (isset($fontMap[$fontFamily])) {
        $css[] = 'font-family:' . $fontMap[$fontFamily];
    }

    $fontSize = (int) ($style['font_size'] ?? 0);
    if ($fontSize >= 10 && $fontSize <= 56) {
        $css[] = 'font-size:' . $fontSize . 'px';
    }

    if (!empty($style['bold'])) {
        $css[] = 'font-weight:700';
    }
    if (!empty($style['italic'])) {
        $css[] = 'font-style:italic';
    }
    if (!empty($style['underline'])) {
        $css[] = 'text-decoration:underline';
    }

    $color = trim((string) ($style['color'] ?? ''));
    if (preg_match('/^#[0-9a-fA-F]{6}$/', $color) === 1) {
        $css[] = 'color:' . strtolower($color);
    }

    if ($css === []) {
        return '';
    }

    return ' style="' . e(implode(';', $css)) . '"';
}
