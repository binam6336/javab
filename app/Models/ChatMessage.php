<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class ChatMessage
{
    /** @param array<string,mixed> $data */
    public static function add(string $conversationId, string $role, string $content, array $data = []): int
    {
        return Database::insert(
            'INSERT INTO `chat_messages` (`conversation_id`, `role`, `content`, `status`, `confidence`, `sources`, `via`, `ip_hash`)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $conversationId,
                $role,
                $content,
                (string) ($data['status'] ?? 'ok'),
                isset($data['confidence']) ? (int) $data['confidence'] : null,
                !empty($data['sources']) ? implode(',', array_filter(array_map('strval', $data['sources']), static function ($k) {
                    return (bool) preg_match('/^c?\d+$/', $k);
                })) : null,
                $data['via'] ?? null,
                $data['ip_hash'] ?? null,
            ]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public static function conversation(string $conversationId, int $limit = 200): array
    {
        $rows = Database::all(
            "SELECT `id`, `role`, `content`, `status`, `confidence`, `sources`, `via`, `created_at`
             FROM `chat_messages` WHERE `conversation_id` = ? ORDER BY `id` DESC LIMIT {$limit}",
            [$conversationId]
        );
        return array_reverse($rows);
    }

    /**
     * چند پیام آخر گفتگو برای درک سوال‌های ادامه‌دار (فقط پیام‌های موفق).
     * @return array<int,array{role:string,content:string}>
     */
    public static function recentContext(string $conversationId, int $limit): array
    {
        if ($limit <= 0) {
            return [];
        }
        $rows = Database::all(
            "SELECT `role`, `content` FROM `chat_messages`
             WHERE `conversation_id` = ? AND `status` IN ('ok','answered')
             ORDER BY `id` DESC LIMIT {$limit}",
            [$conversationId]
        );
        return array_reverse(array_map(static function ($r) {
            return ['role' => (string) $r['role'], 'content' => mb_substr((string) $r['content'], 0, 600)];
        }, $rows));
    }

    /** @return array<string,mixed>|null */
    public static function find(int $id): ?array
    {
        return Database::one('SELECT * FROM `chat_messages` WHERE `id` = ?', [$id]);
    }

    /** سوالِ کاربر که این پیام دستیار پاسخ آن بوده است */
    public static function questionFor(array $assistant): string
    {
        return (string) Database::value(
            "SELECT `content` FROM `chat_messages` WHERE `conversation_id` = ? AND `role` = 'user' AND `id` < ? ORDER BY `id` DESC LIMIT 1",
            [$assistant['conversation_id'], $assistant['id']]
        );
    }

    /** @return array{total:int,answered:int,no_answer:int} */
    public static function stats(): array
    {
        $row = Database::one(
            "SELECT COUNT(*) AS total,
                    SUM(`status` = 'answered') AS answered,
                    SUM(`status` = 'no_answer') AS no_answer
             FROM `chat_messages` WHERE `role` = 'assistant'"
        ) ?? [];
        return [
            'total'     => (int) ($row['total'] ?? 0),
            'answered'  => (int) ($row['answered'] ?? 0),
            'no_answer' => (int) ($row['no_answer'] ?? 0),
        ];
    }
}
