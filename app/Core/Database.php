<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;

/**
 * اتصال PDO به MySQL. اگر دیتابیس وجود نداشته باشد خودش آن را می‌سازد.
 */
final class Database
{
    /** @var PDO|null */
    private static $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            self::connect();
        }
        return self::$pdo;
    }

    private static function connect(): void
    {
        $host = (string) Env::get('DB_HOST', '127.0.0.1');
        $port = Env::int('DB_PORT', 3306);
        $name = (string) Env::get('DB_DATABASE', 'javab');
        $user = (string) Env::get('DB_USERNAME', 'root');
        $pass = (string) Env::get('DB_PASSWORD', '');

        if (!preg_match('/^[A-Za-z0-9_\-]+$/', $name)) {
            throw new RuntimeException('نام دیتابیس در .env نامعتبر است.');
        }

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        $dsn = "mysql:host={$host};port={$port};charset=utf8mb4";

        try {
            self::$pdo = new PDO($dsn . ";dbname={$name}", $user, $pass, $options);
        } catch (PDOException $e) {
            $unknownDb = (int) $e->getCode() === 1049 || stripos($e->getMessage(), 'Unknown database') !== false;
            if (!$unknownDb) {
                throw $e;
            }
            // دیتابیس هنوز ساخته نشده: بسازش
            $pdo = new PDO($dsn, $user, $pass, $options);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `{$name}`");
            self::$pdo = $pdo;
        }

        self::$pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        $offset = (new \DateTime('now', new \DateTimeZone(date_default_timezone_get())))->format('P');
        self::$pdo->exec("SET time_zone = '{$offset}'");
    }

    /** @param array<int|string,mixed> $params */
    public static function run(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /** @return array<string,mixed>|null */
    public static function one(string $sql, array $params = []): ?array
    {
        $row = self::run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /** @return array<int,array<string,mixed>> */
    public static function all(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll();
    }

    /** @return mixed */
    public static function value(string $sql, array $params = [])
    {
        $v = self::run($sql, $params)->fetchColumn();
        return $v === false ? null : $v;
    }

    public static function insert(string $sql, array $params = []): int
    {
        self::run($sql, $params);
        return (int) self::pdo()->lastInsertId();
    }

    /** قفل نام‌دار MySQL (برای جلوگیری از شرایط رقابتی). */
    public static function lock(string $name, int $timeout = 5): bool
    {
        return (int) self::value('SELECT GET_LOCK(?, ?)', [$name, $timeout]) === 1;
    }

    public static function unlock(string $name): void
    {
        self::run('SELECT RELEASE_LOCK(?)', [$name]);
    }
}
