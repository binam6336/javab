<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\App;
use App\Core\Database;
use App\Models\Setting;

/**
 * ریت لیمیت پنجره‌ی لغزان روی جدول rate_events.
 * هم برای کاربران (بر اساس IP) و هم برای کل درخواست‌هایی که به مدل AI ارسال می‌شود.
 */
final class RateLimiter
{
    /**
     * @param array<int,array{0:int,1:int}> $rules [[limit, windowSeconds], ...]
     * @return array{ok:bool,retry_after:int}
     */
    public static function attempt(string $scope, string $key, array $rules): array
    {
        $hash = hash('sha256', (string) App::config('key') . '|' . $key);
        $lock = 'javab_rl_' . substr(hash('sha256', $scope . $hash), 0, 40);
        $locked = Database::lock($lock, 3);

        try {
            foreach ($rules as [$limit, $window]) {
                if ($limit <= 0) {
                    continue;
                }
                $count = (int) Database::value(
                    'SELECT COUNT(*) FROM `rate_events` WHERE `scope` = ? AND `key_hash` = ? AND `created_at` > NOW() - INTERVAL ? SECOND',
                    [$scope, $hash, $window]
                );
                if ($count >= $limit) {
                    $oldest = Database::value(
                        'SELECT TIMESTAMPDIFF(SECOND, NOW(), MIN(`created_at`) + INTERVAL ? SECOND) FROM (
                            SELECT `created_at` FROM `rate_events`
                            WHERE `scope` = ? AND `key_hash` = ? AND `created_at` > NOW() - INTERVAL ? SECOND
                            ORDER BY `created_at` DESC LIMIT ?
                         ) t',
                        [$window, $scope, $hash, $window, $limit]
                    );
                    return ['ok' => false, 'retry_after' => max(1, (int) $oldest)];
                }
            }
            Database::run('INSERT INTO `rate_events` (`scope`, `key_hash`) VALUES (?, ?)', [$scope, $hash]);
        } finally {
            if ($locked) {
                Database::unlock($lock);
            }
        }

        if (random_int(1, 100) === 1) {
            Database::run('DELETE FROM `rate_events` WHERE `created_at` < NOW() - INTERVAL 2 DAY');
        }
        return ['ok' => true, 'retry_after' => 0];
    }

    // ---------------------------------------------------------------
    // مدل هوش مصنوعی
    // ---------------------------------------------------------------

    /** @return array{ok:bool,retry_after:int} */
    public static function aiAttempt(): array
    {
        $cool = self::aiCooldownLeft();
        if ($cool > 0) {
            return ['ok' => false, 'retry_after' => $cool];
        }
        return self::attempt('ai', 'global', [
            [(int) App::config('rate.ai_per_minute', 20), 60],
            [(int) App::config('rate.ai_per_day', 2000), 86400],
        ]);
    }

    /** وقتی مدل 429 برگرداند، تا این مدت هیچ درخواستی ارسال نمی‌شود. */
    public static function aiCooldown(int $seconds): void
    {
        Setting::set('ai_cooldown_until', (string) (time() + max(1, $seconds)));
    }

    public static function aiCooldownLeft(): int
    {
        $until = (int) (Setting::get('ai_cooldown_until', '0') ?? 0);
        return max(0, $until - time());
    }

    /**
     * گرفتن یکی از «جایگاه‌های» هم‌زمانی (حداکثر N درخواست هم‌زمان به مدل).
     * @return string|null نام قفل گرفته‌شده، یا null اگر همه پر بودند.
     */
    public static function acquireAiSlot(int $waitSeconds = 8): ?string
    {
        $slots = max(1, (int) App::config('rate.ai_concurrent', 3));
        $deadline = microtime(true) + $waitSeconds;
        do {
            for ($i = 1; $i <= $slots; $i++) {
                $name = 'javab_ai_slot_' . $i;
                if ((int) Database::value('SELECT GET_LOCK(?, 0)', [$name]) === 1) {
                    return $name;
                }
            }
            usleep(250000);
        } while (microtime(true) < $deadline);
        return null;
    }

    public static function releaseAiSlot(?string $name): void
    {
        if ($name !== null) {
            Database::unlock($name);
        }
    }
}
