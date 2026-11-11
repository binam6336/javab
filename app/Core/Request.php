<?php

declare(strict_types=1);

namespace App\Core;

final class Request
{
    /** @var array<string,mixed>|null */
    private static $json = null;

    public static function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    /** مسیر درخواست بدون پیشوند پوشه‌ی نصب. */
    public static function path(): string
    {
        $uri  = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $base = App::basePath();
        if ($base !== '' && strpos($uri, $base) === 0) {
            $uri = substr($uri, strlen($base));
        }
        if (strpos($uri, '/index.php') === 0) {
            $uri = substr($uri, 10);
        }
        $uri = '/' . trim(rawurldecode($uri), '/');
        return $uri;
    }

    /** @param mixed $default @return mixed */
    public static function input(string $key, $default = null)
    {
        if (self::isJson()) {
            $data = self::json();
            return $data[$key] ?? $default;
        }
        return $_POST[$key] ?? $_GET[$key] ?? $default;
    }

    public static function str(string $key, string $default = ''): string
    {
        $v = self::input($key, $default);
        return is_scalar($v) ? trim((string) $v) : $default;
    }

    public static function query(string $key, string $default = ''): string
    {
        $v = $_GET[$key] ?? $default;
        return is_scalar($v) ? trim((string) $v) : $default;
    }

    public static function isJson(): bool
    {
        return stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false;
    }

    public static function wantsJson(): bool
    {
        return self::isJson()
            || stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false
            || strpos(self::path(), '/api/') === 0;
    }

    /** @return array<string,mixed> */
    public static function json(): array
    {
        if (self::$json === null) {
            $raw = (string) file_get_contents('php://input');
            $data = json_decode($raw, true);
            self::$json = is_array($data) ? $data : [];
        }
        return self::$json;
    }

    public static function ip(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        if (Env::bool('TRUST_PROXY', false)) {
            $fwd = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
            if ($fwd !== '') {
                $first = trim(explode(',', $fwd)[0]);
                if (filter_var($first, FILTER_VALIDATE_IP)) {
                    $ip = $first;
                }
            }
        }
        return $ip;
    }
}
