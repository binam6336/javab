<?php

declare(strict_types=1);

use App\Core\App;
use App\Core\Csrf;

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = '/'): string
{
    return App::basePath() . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    $file = BASE_PATH . '/public/assets/' . ltrim($path, '/');
    $v = is_file($file) ? (string) filemtime($file) : '1';
    return App::assetBase() . '/assets/' . ltrim($path, '/') . '?v=' . $v;
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(Csrf::token()) . '">';
}

function config(string $key, $default = null)
{
    return App::config($key, $default);
}

/** عددهای لاتین → فارسی برای نمایش */
function fa_num($value): string
{
    return strtr((string) $value, ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹']);
}

function short_text(string $text, int $limit = 120): string
{
    $text = trim((string) preg_replace('/\s+/u', ' ', $text));
    return mb_strlen($text) > $limit ? mb_substr($text, 0, $limit) . '…' : $text;
}

/** آیکن‌های SVG ساده (بدون وابستگی خارجی) */
function icon(string $name, string $class = 'ic'): string
{
    $paths = [
        'chart'   => '<path d="M4 20V4M4 20h16"/><path d="M8 16v-4M12 16V8M16 16v-6"/>',
        'alert'   => '<path d="M12 4l9 16H3z"/><path d="M12 10v4M12 17v.5"/>',
        'link'    => '<path d="M10 14a4 4 0 0 0 5.6 0l3-3a4 4 0 0 0-5.6-5.6l-1 1"/><path d="M14 10a4 4 0 0 0-5.6 0l-3 3a4 4 0 0 0 5.6 5.6l1-1"/>',
        'rules'   => '<path d="M9 6h11M9 12h11M9 18h11"/><path d="M4 6l1 1 2-2M4 12l1 1 2-2M4 18l1 1 2-2"/>',
        'chat'    => '<path d="M4 5h16v11H8l-4 4z"/>',
        'book'    => '<path d="M5 4h11a3 3 0 0 1 3 3v13H8a3 3 0 0 1-3-3z"/><path d="M5 17a3 3 0 0 1 3-3h11"/>',
        'help'    => '<circle cx="12" cy="12" r="9"/><path d="M9.5 9.5a2.5 2.5 0 1 1 3.5 2.3c-.7.3-1 .9-1 1.7v.5"/><path d="M12 17.5h.01"/>',
        'plus'    => '<path d="M12 5v14M5 12h14"/>',
        'send'    => '<path d="M12 19V5M6 11l6-6 6 6"/>',
        'menu'    => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'close'   => '<path d="M6 6l12 12M18 6L6 18"/>',
        'edit'    => '<path d="M4 20h4L19 9l-4-4L4 16z"/>',
        'trash'   => '<path d="M5 7h14M10 7V4h4v3M7 7l1 13h8l1-13"/>',
        'search'  => '<circle cx="11" cy="11" r="6"/><path d="M20 20l-4.5-4.5"/>',
        'spark'   => '<path d="M12 3l1.8 5.2L19 10l-5.2 1.8L12 17l-1.8-5.2L5 10l5.2-1.8z"/><path d="M19 16l.8 2.2L22 19l-2.2.8L19 22l-.8-2.2L16 19l2.2-.8z"/>',
        'check'   => '<path d="M5 12l5 5 9-10"/>',
        'clock'   => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'copy'    => '<rect x="8" y="8" width="12" height="12" rx="2"/><path d="M16 8V5a1 1 0 0 0-1-1H5a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h3"/>',
        'user'    => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
    ];
    $p = $paths[$name] ?? '';
    return '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p . '</svg>';
}
