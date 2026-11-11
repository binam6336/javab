<?php

declare(strict_types=1);

namespace App\Core;

use PDOException;
use Throwable;

final class App
{
    /** @var array<string,mixed> */
    private static $config = [];

    public static function run(): void
    {
        $envLoaded = Env::load(BASE_PATH . '/.env');
        self::$config = require BASE_PATH . '/config/app.php';
        date_default_timezone_set((string) self::config('timezone', 'Asia/Tehran'));
        mb_internal_encoding('UTF-8');

        $debug = (bool) self::config('debug', false);
        ini_set('display_errors', $debug ? '1' : '0');
        error_reporting(E_ALL);

        self::securityHeaders();

        try {
            if (!$envLoaded) {
                Response::error('فایل .env پیدا نشد. فایل .env.example را به .env کپی کنید و اطلاعات دیتابیس را در آن بنویسید.', 500);
            }

            // ساخت خودکار دیتابیس و جدول‌ها در اولین اجرا
            Migrator::run();

            Session::start();
            $router = new Router();
            require BASE_PATH . '/config/routes.php';
            $router->dispatch(Request::method(), Request::path());
        } catch (PDOException $e) {
            Logger::error('خطای دیتابیس', ['message' => $e->getMessage(), 'code' => $e->getCode()]);
            // جدول وجود ندارد؟ (مثلاً دیتابیس دستی پاک شده) → دفعه‌ی بعد migration دوباره بررسی شود
            if ($e->getCode() === '42S02') {
                Migrator::reset();
            }
            $msg = $debug ? $e->getMessage() : 'اتصال به دیتابیس برقرار نشد. اطلاعات DB_* را در فایل .env بررسی کنید.';
            Response::error($msg, 500);
        } catch (Throwable $e) {
            Logger::error('خطای کلی', ['message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
            Response::error($debug ? $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() : 'خطای غیرمنتظره‌ای رخ داد.', 500);
        }
    }

    /** @param mixed $default @return mixed */
    public static function config(string $key, $default = null)
    {
        $value = self::$config;
        foreach (explode('.', $key) as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return $default;
            }
            $value = $value[$part];
        }
        return $value;
    }

    /** پوشه‌ی نصب (برای نصب در زیرپوشه). خالی یعنی ریشه‌ی دامنه. */
    public static function basePath(): string
    {
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
        $dir = rtrim(dirname($script), '/');
        return $dir === '.' ? '' : $dir;
    }

    /** آدرس پوشه‌ی public (اگر سایت از ریشه‌ی پروژه اجرا شود، assets داخل /public است). */
    public static function assetBase(): string
    {
        return self::basePath() . (defined('JAVAB_ROOT_FRONT') ? '/public' : '');
    }

    private static function securityHeaders(): void
    {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: same-origin');
        header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' https://cdn.jsdelivr.net; font-src 'self' https://cdn.jsdelivr.net data:; img-src 'self' data:; connect-src 'self'; frame-ancestors 'self'; base-uri 'self'; form-action 'self'");
    }
}
