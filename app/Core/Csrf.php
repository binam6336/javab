<?php

declare(strict_types=1);

namespace App\Core;

/**
 * سیستم احراز هویت ندارد، اما فرم‌ها و API در برابر CSRF محافظت می‌شوند.
 */
final class Csrf
{
    public static function token(): string
    {
        Session::start();
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public static function verify(): void
    {
        $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? Request::str('_token');
        if (!is_string($sent) || $sent === '' || !hash_equals(self::token(), $sent)) {
            Response::error('نشست شما منقضی شده است؛ صفحه را دوباره بارگذاری کنید.', 419);
        }
    }
}
