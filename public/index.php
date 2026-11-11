<?php

declare(strict_types=1);

/*
 * نقطه‌ی ورود برنامه.
 * در اولین اجرا، دیتابیس (اگر وجود نداشته باشد) و همه‌ی جدول‌ها خودکار ساخته می‌شوند.
 */

if (PHP_VERSION_ID < 70400) {
    http_response_code(500);
    exit('این برنامه به PHP 7.4 یا بالاتر نیاز دارد.');
}

define('BASE_PATH', dirname(__DIR__));

spl_autoload_register(static function (string $class): void {
    if (strpos($class, 'App\\') !== 0) {
        return;
    }
    $file = BASE_PATH . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require BASE_PATH . '/app/Core/helpers.php';

App\Core\App::run();
