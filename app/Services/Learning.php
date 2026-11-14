<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Correction;
use App\Models\Feedback;

/**
 * یادگیری از بازخوردها: اصلاح‌ها و امتیازهای مرتبط با سوال جدید را پیدا می‌کند
 * تا در پاسخ‌دهی استفاده شوند (اصلاح‌ها مقدم بر دانشنامه‌اند؛ بازخوردها راهنمای کیفیت پاسخ).
 */
final class Learning
{
    /** حداقل شباهت سوال برای این‌که یک اصلاح به مدل نشان داده شود (تشخیص نهایی هم‌معنایی با مدل است) */
    private const CORRECTION_MIN = 0.35;
    /** حداقل شباهت سوال برای استفاده از یک بازخورد */
    private const FEEDBACK_MIN = 0.5;
    private const MAX_ITEMS = 3;

    /**
     * @return array{corrections:array<int,array<string,mixed>>,feedback:array<int,array<string,mixed>>}
     *   هر مورد کلید score (شباهت سوال، ۰ تا ۱) دارد؛ مرتب از بیشترین.
     */
    public static function relevant(string $question): array
    {
        $qNorm = TextNormalizer::normalize($question);
        if ($qNorm === '') {
            return ['corrections' => [], 'feedback' => []];
        }
        $qTokens = TextNormalizer::tokens($qNorm);
        $qTri = TextNormalizer::trigrams(TextNormalizer::compact($qNorm));

        $score = static function (array $row) use ($qNorm, $qTokens, $qTri): float {
            $rNorm = (string) $row['question_norm'];
            return Retriever::questionSimilarity($qNorm, $qTokens, $qTri, $rNorm, TextNormalizer::tokens($rNorm));
        };

        $corrections = [];
        foreach (Correction::active() as $row) {
            $s = $score($row);
            if ($s >= self::CORRECTION_MIN) {
                $row['score'] = $s;
                $corrections[] = $row;
            }
        }
        usort($corrections, static function ($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        $feedback = [];
        foreach (Feedback::influential() as $row) {
            $s = $score($row);
            if ($s >= self::FEEDBACK_MIN) {
                $row['score'] = $s;
                $feedback[] = $row;
            }
        }
        usort($feedback, static function ($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        return [
            'corrections' => array_slice($corrections, 0, self::MAX_ITEMS),
            'feedback'    => array_slice($feedback, 0, self::MAX_ITEMS),
        ];
    }
}
