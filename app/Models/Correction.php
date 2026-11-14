<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Services\TextNormalizer;

/** اصلاح پاسخ‌ها: پاسخ درستی که جایگزین پاسخ اشتباه دستیار شده است */
final class Correction
{
    public const ANSWER_MAX = 5000;

    /** @return array<string,mixed>|null */
    public static function find(int $id): ?array
    {
        return Database::one('SELECT * FROM `answer_corrections` WHERE `id` = ?', [$id]);
    }

    /** @return array<string,mixed>|null */
    public static function forMessage(int $messageId): ?array
    {
        return Database::one('SELECT * FROM `answer_corrections` WHERE `message_id` = ?', [$messageId]);
    }

    /**
     * @param int[] $messageIds
     * @return array<int,array<string,mixed>> کلید = message_id
     */
    public static function forMessages(array $messageIds): array
    {
        $messageIds = array_values(array_unique(array_map('intval', $messageIds)));
        if (!$messageIds) {
            return [];
        }
        $in = implode(',', array_fill(0, count($messageIds), '?'));
        $out = [];
        foreach (Database::all("SELECT `id`, `message_id`, `corrected_answer`, `is_active` FROM `answer_corrections` WHERE `message_id` IN ({$in})", $messageIds) as $r) {
            $out[(int) $r['message_id']] = $r;
        }
        return $out;
    }

    /**
     * @param int[] $ids
     * @return array<int,array<string,mixed>> کلید = id
     */
    public static function findMany(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (!$ids) {
            return [];
        }
        $in = implode(',', array_fill(0, count($ids), '?'));
        $out = [];
        foreach (Database::all("SELECT `id`, `question`, `corrected_answer` FROM `answer_corrections` WHERE `id` IN ({$in})", $ids) as $r) {
            $out[(int) $r['id']] = $r;
        }
        return $out;
    }

    /** ثبت یا به‌روزرسانی اصلاح یک پیام @return int شناسه‌ی اصلاح */
    public static function saveForMessage(int $messageId, string $conversationId, string $question, string $original, string $corrected): int
    {
        Database::run(
            'INSERT INTO `answer_corrections` (`message_id`, `conversation_id`, `question`, `question_norm`, `original_answer`, `corrected_answer`)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE `corrected_answer` = VALUES(`corrected_answer`), `is_active` = 1',
            [$messageId, $conversationId, $question, TextNormalizer::normalize($question), $original, $corrected]
        );
        Setting::bumpKbRevision();
        return (int) Database::value('SELECT `id` FROM `answer_corrections` WHERE `message_id` = ?', [$messageId]);
    }

    public static function update(int $id, string $question, string $corrected): void
    {
        Database::run(
            'UPDATE `answer_corrections` SET `question` = ?, `question_norm` = ?, `corrected_answer` = ? WHERE `id` = ?',
            [$question, TextNormalizer::normalize($question), $corrected, $id]
        );
        Setting::bumpKbRevision();
    }

    public static function setActive(int $id, bool $active): void
    {
        Database::run('UPDATE `answer_corrections` SET `is_active` = ? WHERE `id` = ?', [$active ? 1 : 0, $id]);
        Setting::bumpKbRevision();
    }

    public static function delete(int $id): void
    {
        Database::run('DELETE FROM `answer_corrections` WHERE `id` = ?', [$id]);
        Setting::bumpKbRevision();
    }

    /** @return array<int,array<string,mixed>> */
    public static function active(int $limit = 3000): array
    {
        return Database::all(
            "SELECT `id`, `question`, `question_norm`, `original_answer`, `corrected_answer` FROM `answer_corrections`
             WHERE `is_active` = 1 ORDER BY `id` DESC LIMIT {$limit}"
        );
    }

    /** @param int[] $ids */
    public static function addHits(array $ids): void
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if ($ids) {
            $in = implode(',', array_fill(0, count($ids), '?'));
            Database::run("UPDATE `answer_corrections` SET `hits` = `hits` + 1 WHERE `id` IN ({$in})", $ids);
        }
    }

    /** @return array{rows:array<int,array<string,mixed>>,total:int,pages:int,page:int} */
    public static function paginate(int $page, int $perPage = 20): array
    {
        $total = (int) Database::value('SELECT COUNT(*) FROM `answer_corrections`');
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);
        $offset = ($page - 1) * $perPage;
        $rows = Database::all("SELECT * FROM `answer_corrections` ORDER BY `updated_at` DESC, `id` DESC LIMIT {$perPage} OFFSET {$offset}");
        return ['rows' => $rows, 'total' => $total, 'pages' => $pages, 'page' => $page];
    }

    public static function count(): int
    {
        return (int) Database::value('SELECT COUNT(*) FROM `answer_corrections`');
    }
}
