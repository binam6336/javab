<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Services\TextNormalizer;

final class Knowledge
{
    public const QUESTION_MAX = 1000;
    public const ANSWER_MAX   = 5000;

    public static function count(): int
    {
        return (int) Database::value('SELECT COUNT(*) FROM `knowledge`');
    }

    /** @return array<string,mixed>|null */
    public static function find(int $id): ?array
    {
        return Database::one('SELECT * FROM `knowledge` WHERE `id` = ?', [$id]);
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
        $rows = Database::all("SELECT `id`, `question`, `answer` FROM `knowledge` WHERE `id` IN ({$in})", $ids);
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['id']] = $r;
        }
        return $out;
    }

    /** @return array{rows:array<int,array<string,mixed>>,total:int,pages:int,page:int} */
    public static function paginate(string $search, int $page, int $perPage = 20): array
    {
        $where = '';
        $params = [];
        if ($search !== '') {
            $words = array_slice(TextNormalizer::words(TextNormalizer::normalize($search)), 0, 6);
            $parts = [];
            foreach ($words as $w) {
                $parts[] = '(`question_norm` LIKE ? OR `answer_norm` LIKE ?)';
                $like = '%' . addcslashes($w, '%_\\') . '%';
                $params[] = $like;
                $params[] = $like;
            }
            if ($parts) {
                $where = 'WHERE ' . implode(' AND ', $parts);
            }
        }
        $total = (int) Database::value("SELECT COUNT(*) FROM `knowledge` {$where}", $params);
        $pages = max(1, (int) ceil($total / $perPage));
        $page  = min(max(1, $page), $pages);
        $offset = ($page - 1) * $perPage;
        $rows = Database::all(
            "SELECT `id`, `question`, `answer`, `hits`, `updated_at` FROM `knowledge` {$where}
             ORDER BY `id` DESC LIMIT {$perPage} OFFSET {$offset}",
            $params
        );
        return ['rows' => $rows, 'total' => $total, 'pages' => $pages, 'page' => $page];
    }

    /** برای جستجوی معنایی: فقط ستون‌های نرمال‌شده. @return array<int,array<string,mixed>> */
    public static function forRetrieval(int $limit): array
    {
        return Database::all(
            "SELECT `id`, `question_norm`, `answer_norm` FROM `knowledge` ORDER BY `id` DESC LIMIT {$limit}"
        );
    }

    /** @return string[] */
    public static function samples(int $n = 4): array
    {
        return array_map(static function ($r) {
            return (string) $r['question'];
        }, Database::all("SELECT `question` FROM `knowledge` ORDER BY `hits` DESC, RAND() LIMIT {$n}"));
    }

    public static function create(string $question, string $answer): int
    {
        $id = Database::insert(
            'INSERT INTO `knowledge` (`question`, `answer`, `question_norm`, `answer_norm`) VALUES (?, ?, ?, ?)',
            [$question, $answer, TextNormalizer::normalize($question), TextNormalizer::normalize($answer)]
        );
        Setting::bumpKbRevision();
        return $id;
    }

    public static function update(int $id, string $question, string $answer): void
    {
        Database::run(
            'UPDATE `knowledge` SET `question` = ?, `answer` = ?, `question_norm` = ?, `answer_norm` = ? WHERE `id` = ?',
            [$question, $answer, TextNormalizer::normalize($question), TextNormalizer::normalize($answer), $id]
        );
        Setting::bumpKbRevision();
    }

    public static function delete(int $id): void
    {
        Database::run('DELETE FROM `knowledge` WHERE `id` = ?', [$id]);
        Setting::bumpKbRevision();
    }

    /** @param int[] $ids */
    public static function addHits(array $ids): void
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (!$ids) {
            return;
        }
        $in = implode(',', array_fill(0, count($ids), '?'));
        Database::run("UPDATE `knowledge` SET `hits` = `hits` + 1, `updated_at` = `updated_at` WHERE `id` IN ({$in})", $ids);
    }

    public static function existsQuestion(string $question, int $exceptId = 0): bool
    {
        return (bool) Database::value(
            'SELECT 1 FROM `knowledge` WHERE `question_norm` = ? AND `id` <> ? LIMIT 1',
            [TextNormalizer::normalize($question), $exceptId]
        );
    }
}
