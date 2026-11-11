<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

/**
 * اجرای خودکار migrationها در اولین درخواست به صفحه‌ی اصلی.
 * هر فایل database/migrations/NNN_*.sql یک‌بار اجرا و در جدول migrations ثبت می‌شود.
 * پس از اجرا یک فایل پرچم در storage/cache نوشته می‌شود تا درخواست‌های بعدی سریع باشند.
 */
final class Migrator
{
    public static function run(bool $force = false): void
    {
        $files = glob(BASE_PATH . '/database/migrations/*.sql') ?: [];
        sort($files);
        $signature = md5(implode('|', array_map('basename', $files)));
        $flag = self::flagDir() . '/migrated-' . $signature;

        if (!$force && is_file($flag)) {
            return;
        }

        $pdo = Database::pdo();
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS `migrations` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `name` VARCHAR(190) NOT NULL UNIQUE,
                `ran_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $locked = Database::lock('javab_migrate', 15);
        try {
            $done = $pdo->query('SELECT `name` FROM `migrations`')->fetchAll(PDO::FETCH_COLUMN);
            foreach ($files as $file) {
                $name = basename($file);
                if (in_array($name, $done, true)) {
                    continue;
                }
                foreach (self::split((string) file_get_contents($file)) as $statement) {
                    $pdo->exec($statement);
                }
                Database::run('INSERT INTO `migrations` (`name`) VALUES (?)', [$name]);
                Logger::info('migration اجرا شد', ['file' => $name]);
            }
        } finally {
            if ($locked) {
                Database::unlock('javab_migrate');
            }
        }

        @file_put_contents($flag, date('c'));
    }

    /** حذف فایل‌های پرچم تا در درخواست بعدی migrationها دوباره بررسی شوند. */
    public static function reset(): void
    {
        foreach (glob(self::flagDir() . '/migrated-*') ?: [] as $f) {
            @unlink($f);
        }
    }

    private static function flagDir(): string
    {
        $dir = BASE_PATH . '/storage/cache';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir;
    }

    /** @return string[] */
    private static function split(string $sql): array
    {
        $sql = (string) preg_replace('/^\s*--.*$/m', '', $sql);
        $parts = preg_split('/;\s*(\r?\n|$)/', $sql) ?: [];
        return array_values(array_filter(array_map('trim', $parts), static function ($s) {
            return $s !== '';
        }));
    }
}
