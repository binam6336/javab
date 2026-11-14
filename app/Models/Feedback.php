<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Services\TextNormalizer;

/** امتیاز و بازخورد کاربر روی پاسخ‌های دستیار */
final class Feedback
{
    public const COMMENT_MAX = 1000;

    /** @return array<string,mixed>|null */
    public static function forMessage(int $messageId): ?array
    {
        return Database::one('SELECT * FROM `answer_feedback` WHERE `message_id` = ?', [$messageId]);
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
        foreach (Database::all("SELECT `message_id`, `rating`, `comment` FROM `answer_feedback` WHERE `message_id` IN ({$in})", $messageIds) as $r) {
            $out[(int) $r['message_id']] = $r;
        }
        return $out;
    }

    public static function save(int $messageId, string $conversationId, string $question, string $answer, ?int $rating, ?string $comment): void
    {
        Database::run(
            'INSERT INTO `answer_feedback` (`message_id`, `conversation_id`, `question`, `question_norm`, `answer`, `rating`, `comment`)
             VALUES (?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE `rating` = VALUES(`rating`), `comment` = VALUES(`comment`), `answer` = VALUES(`answer`), `is_active` = 1',
            [$messageId, $conversationId, $question, TextNormalizer::normalize($question), $answer, $rating, $comment]
        );
        Setting::bumpKbRevision();
    }

    /** @return array{rows:array<int,array<string,mixed>>,total:int,pages:int,page:int} */
    public static function paginate(string $filter, int $page, int $perPage = 20): array
    {
        $where = ['1 = 1'];
        if ($filter === 'low') {
            $where[] = '`rating` <= 2';
        } elseif ($filter === 'high') {
            $where[] = '`rating` >= 4';
        } elseif ($filter === 'comment') {
            $where[] = "`comment` IS NOT NULL AND `comment` <> ''";
        }
        $w = implode(' AND ', $where);
        $total = (int) Database::value("SELECT COUNT(*) FROM `answer_feedback` WHERE {$w}");
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);
        $offset = ($page - 1) * $perPage;
        $rows = Database::all("SELECT * FROM `answer_feedback` WHERE {$w} ORDER BY `updated_at` DESC, `id` DESC LIMIT {$perPage} OFFSET {$offset}");
        return ['rows' => $rows, 'total' => $total, 'pages' => $pages, 'page' => $page];
    }

    /** @return array{count:int,avg:float,dist:array<int,int>} */
    public static function summary(): array
    {
        $dist = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
        foreach (Database::all('SELECT `rating`, COUNT(*) AS c FROM `answer_feedback` WHERE `rating` IS NOT NULL GROUP BY `rating`') as $r) {
            $dist[(int) $r['rating']] = (int) $r['c'];
        }
        $count = array_sum($dist);
        $sum = 0;
        foreach ($dist as $star => $c) {
            $sum += $star * $c;
        }
        return ['count' => $count, 'avg' => $count ? round($sum / $count, 1) : 0.0, 'dist' => $dist];
    }

    /** بازخوردهای اثرگذار (امتیاز کم یا زیاد، یا دارای توضیح) برای مقایسه با سوال جدید @return array<int,array<string,mixed>> */
    public static function influential(int $limit = 3000): array
    {
        return Database::all(
            "SELECT `id`, `message_id`, `question`, `question_norm`, `answer`, `rating`, `comment` FROM `answer_feedback`
             WHERE `is_active` = 1 AND (`rating` IN (1, 2, 4, 5) OR (`comment` IS NOT NULL AND `comment` <> ''))
             ORDER BY `id` DESC LIMIT {$limit}"
        );
    }

    public static function setActive(int $id, bool $active): void
    {
        Database::run('UPDATE `answer_feedback` SET `is_active` = ? WHERE `id` = ?', [$active ? 1 : 0, $id]);
        Setting::bumpKbRevision();
    }

    public static function delete(int $id): void
    {
        Database::run('DELETE FROM `answer_feedback` WHERE `id` = ?', [$id]);
        Setting::bumpKbRevision();
    }
}
