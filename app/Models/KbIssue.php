<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * هشدارهای محتوایی دانشنامه (نتیجه‌ی آخرین تحلیل) و استثناها.
 * استثنا فقط هشدار را از لیست فعال کنار می‌گذارد؛ محتوای دانشنامه تغییر نمی‌کند.
 */
final class KbIssue
{
    public const TYPES = [
        'ambiguity'     => 'چندمعنایی',
        'vague'         => 'ابهام در توضیح یا دستورالعمل',
        'contradiction' => 'تناقض با دانشنامه‌ی دیگر',
        'overlap'       => 'هم‌پوشانی مفهومی',
    ];

    /** اطلاعات آخرین تحلیل کامل‌شده @return array<string,mixed>|null */
    public static function lastRun(): ?array
    {
        $raw = Setting::get('kb_analysis_last');
        $d = $raw ? json_decode($raw, true) : null;
        return is_array($d) ? $d : null;
    }

    public static function lastRunId(): string
    {
        $last = self::lastRun();
        return $last ? (string) $last['run'] : '';
    }

    /** تعداد دانشنامه‌هایی که هشدار فعال (غیر استثنا) دارند — برای سایدبار. تناقض/هم‌پوشانی برای هر دو طرف شمرده می‌شود. */
    public static function activeCount(): int
    {
        $run = self::lastRunId();
        if ($run === '') {
            return 0;
        }
        $ids = [];
        foreach (Database::all('SELECT `knowledge_id`, `type`, `related` FROM `kb_issues` WHERE `run_id` = ?', [$run]) as $r) {
            $ids[(int) $r['knowledge_id']] = true;
            if (in_array($r['type'], ['contradiction', 'overlap'], true) && $r['related'] !== '') {
                $ids[(int) explode(',', (string) $r['related'])[0]] = true;
            }
        }
        if (!$ids) {
            return 0;
        }
        $in = implode(',', array_fill(0, count($ids), '?'));
        return (int) Database::value(
            "SELECT COUNT(*) FROM `knowledge` k LEFT JOIN `kb_exceptions` x ON x.`knowledge_id` = k.`id`
             WHERE k.`id` IN ({$in}) AND x.`knowledge_id` IS NULL",
            array_keys($ids)
        );
    }

    /** @return array<int,array<string,mixed>> هشدارهای آخرین تحلیل */
    public static function forRun(string $run): array
    {
        if ($run === '') {
            return [];
        }
        return Database::all('SELECT * FROM `kb_issues` WHERE `run_id` = ? ORDER BY `knowledge_id`, `id`', [$run]);
    }

    /** @param array<int,array<string,mixed>> $issues */
    public static function insertMany(string $run, array $issues): void
    {
        foreach ($issues as $i) {
            Database::run(
                'INSERT INTO `kb_issues` (`run_id`, `knowledge_id`, `type`, `reason`, `related`, `excerpt`, `related_excerpt`, `content_crc`, `related_crc`)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$run, $i['knowledge_id'], $i['type'], $i['reason'], implode(',', $i['related']), $i['excerpt'], $i['related_excerpt'], $i['content_crc'], $i['related_crc']]
            );
        }
    }

    /** حذف نتایج همه‌ی تحلیل‌ها به‌جز یک run */
    public static function keepOnly(string $run): void
    {
        Database::run('DELETE FROM `kb_issues` WHERE `run_id` <> ?', [$run]);
    }

    public static function deleteRun(string $run): void
    {
        Database::run('DELETE FROM `kb_issues` WHERE `run_id` = ?', [$run]);
    }

    // ---------------- استثناها ----------------

    /** @return array<int,string> knowledge_id => created_at */
    public static function exceptions(): array
    {
        $out = [];
        foreach (Database::all('SELECT `knowledge_id`, `created_at` FROM `kb_exceptions` ORDER BY `created_at` DESC') as $r) {
            $out[(int) $r['knowledge_id']] = (string) $r['created_at'];
        }
        return $out;
    }

    public static function addException(int $knowledgeId): void
    {
        Database::run('INSERT IGNORE INTO `kb_exceptions` (`knowledge_id`) VALUES (?)', [$knowledgeId]);
    }

    public static function removeException(int $knowledgeId): void
    {
        Database::run('DELETE FROM `kb_exceptions` WHERE `knowledge_id` = ?', [$knowledgeId]);
    }
}
