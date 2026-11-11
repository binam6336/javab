<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Services\TextNormalizer;

final class Unanswered
{
    public static function hash(string $question): string
    {
        return hash('sha256', TextNormalizer::compact(TextNormalizer::normalize($question)));
    }

    public static function record(string $question, ?int $confidence): void
    {
        Database::run(
            'INSERT INTO `unanswered` (`question_hash`, `question`, `best_confidence`) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE
                `asks` = `asks` + 1,
                `last_asked_at` = NOW(),
                `resolved_at` = NULL,
                `question` = VALUES(`question`),
                `best_confidence` = GREATEST(COALESCE(`best_confidence`, 0), COALESCE(VALUES(`best_confidence`), 0))',
            [self::hash($question), mb_substr($question, 0, 1000), $confidence]
        );
    }

    public static function openCount(): int
    {
        return (int) Database::value('SELECT COUNT(*) FROM `unanswered` WHERE `resolved_at` IS NULL');
    }

    /** @return array<int,array<string,mixed>> */
    public static function open(int $limit = 200): array
    {
        return Database::all(
            "SELECT * FROM `unanswered` WHERE `resolved_at` IS NULL
             ORDER BY `asks` DESC, `last_asked_at` DESC LIMIT {$limit}"
        );
    }

    /** @return array<string,mixed>|null */
    public static function find(int $id): ?array
    {
        return Database::one('SELECT * FROM `unanswered` WHERE `id` = ?', [$id]);
    }

    public static function resolve(int $id): void
    {
        Database::run('UPDATE `unanswered` SET `resolved_at` = NOW() WHERE `id` = ?', [$id]);
    }

    public static function resolveByQuestion(string $question): void
    {
        Database::run(
            'UPDATE `unanswered` SET `resolved_at` = NOW() WHERE `question_hash` = ? AND `resolved_at` IS NULL',
            [self::hash($question)]
        );
    }
}
