<?php

declare(strict_types=1);

namespace App\Core;

/**
 * خواندن فایل .env (KEY=VALUE) بدون نیاز به کتابخانه‌ی خارجی.
 */
final class Env
{
    /** @var array<string,string> */
    private static $vars = [];

    public static function load(string $file): bool
    {
        if (!is_file($file)) {
            return false;
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $key   = trim($key);
            $value = trim($value);

            if (preg_match('/^"(.*)"$/s', $value, $m)) {
                $value = stripcslashes($m[1]);
            } elseif (preg_match("/^'(.*)'$/s", $value, $m)) {
                $value = $m[1];
            } else {
                $value = (string) preg_replace('/\s+#.*$/', '', $value);
            }
            self::$vars[$key] = $value;
        }
        return true;
    }

    /**
     * @param mixed $default
     * @return mixed
     */
    public static function get(string $key, $default = null)
    {
        $value = self::$vars[$key] ?? getenv($key);
        if ($value === false || $value === null || $value === '') {
            return $default;
        }
        switch (strtolower((string) $value)) {
            case 'true':
                return true;
            case 'false':
                return false;
            case 'null':
                return null;
        }
        return $value;
    }

    public static function int(string $key, int $default): int
    {
        $v = self::get($key);
        return is_numeric($v) ? (int) $v : $default;
    }

    public static function float(string $key, float $default): float
    {
        $v = self::get($key);
        return is_numeric($v) ? (float) $v : $default;
    }

    public static function bool(string $key, bool $default): bool
    {
        $v = self::get($key);
        return is_bool($v) ? $v : ($v === null ? $default : in_array(strtolower((string) $v), ['1', 'yes', 'on'], true));
    }
}
