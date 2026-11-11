<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Setting
{
    public static function get(string $key, ?string $default = null): ?string
    {
        $v = Database::value('SELECT `value` FROM `settings` WHERE `key` = ?', [$key]);
        return $v === null ? $default : (string) $v;
    }

    public static function set(string $key, ?string $value): void
    {
        Database::run(
            'INSERT INTO `settings` (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)',
            [$key, $value]
        );
    }

    /** نسخه‌ی دانشنامه؛ با هر تغییر عوض می‌شود تا کش پاسخ‌ها باطل شود. */
    public static function kbRevision(): string
    {
        return self::get('kb_revision', '0') ?? '0';
    }

    public static function bumpKbRevision(): void
    {
        self::set('kb_revision', bin2hex(random_bytes(8)));
    }
}
