<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * قوانین ثابت: دستورهایی که روی همه‌ی پاسخ‌های دستیار اعمال می‌شوند
 * (لحن، قالب، جمله‌ی پایانی و ...). هر تغییر، کش پاسخ‌ها را باطل می‌کند.
 */
final class Rule
{
    public const BODY_MAX = 1000;
    public const MAX_COUNT = 50;

    /** @return array<int,array<string,mixed>> */
    public static function all(): array
    {
        return Database::all('SELECT `id`, `body`, `is_active`, `created_at` FROM `rules` ORDER BY `sort`, `id`');
    }

    /** @return string[] متن قوانین فعال به ترتیب */
    public static function activeBodies(): array
    {
        $rows = Database::all('SELECT `body` FROM `rules` WHERE `is_active` = 1 ORDER BY `sort`, `id`');
        return array_values(array_filter(array_map(static function ($r) {
            return trim((string) $r['body']);
        }, $rows), 'strlen'));
    }

    public static function count(): int
    {
        return (int) Database::value('SELECT COUNT(*) FROM `rules`');
    }

    /** @return array<string,mixed>|null */
    public static function find(int $id): ?array
    {
        return Database::one('SELECT `id`, `body`, `is_active`, `created_at` FROM `rules` WHERE `id` = ?', [$id]);
    }

    public static function create(string $body): int
    {
        $sort = (int) Database::value('SELECT COALESCE(MAX(`sort`), 0) + 1 FROM `rules`');
        $id = Database::insert('INSERT INTO `rules` (`body`, `sort`) VALUES (?, ?)', [$body, $sort]);
        Setting::bumpKbRevision();
        return $id;
    }

    public static function update(int $id, string $body, bool $active): void
    {
        Database::run('UPDATE `rules` SET `body` = ?, `is_active` = ? WHERE `id` = ?', [$body, $active ? 1 : 0, $id]);
        Setting::bumpKbRevision();
    }

    public static function delete(int $id): void
    {
        Database::run('DELETE FROM `rules` WHERE `id` = ?', [$id]);
        Setting::bumpKbRevision();
    }

    /** @param array<string,mixed> $row @return array{id:int,body:string,active:bool} */
    public static function out(array $row): array
    {
        return ['id' => (int) $row['id'], 'body' => (string) $row['body'], 'active' => (int) $row['is_active'] === 1];
    }
}
