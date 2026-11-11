<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Knowledge;

/**
 * پیدا کردن نزدیک‌ترین پرسش‌وپاسخ‌های دانشنامه به سوال کاربر (بدون AI).
 * مرحله‌ی ۱: امتیاز سریع (سه‌حرفی + کلمه‌ی مشترک) روی همه‌ی ردیف‌ها.
 * مرحله‌ی ۲: امتیاز دقیق (با تحمل غلط تایپی) روی بهترین‌ها.
 */
final class Retriever
{
    private const PRESELECT = 60;

    /**
     * @return array<int,array{id:int,score:float,question_score:float}> مرتب از بیشترین امتیاز
     */
    public static function search(string $question, int $topK, int $scanLimit): array
    {
        $qNorm    = TextNormalizer::normalize($question);
        $qTokens  = TextNormalizer::tokens($qNorm);
        $qTri     = TextNormalizer::trigrams(TextNormalizer::compact($qNorm));
        if ($qNorm === '') {
            return [];
        }
        $qTokenSet = array_flip($qTokens);

        // مرحله‌ی ۱
        $pre = [];
        foreach (Knowledge::forRetrieval($scanLimit) as $row) {
            $rNorm = (string) $row['question_norm'];
            $rTri  = TextNormalizer::trigrams(TextNormalizer::compact($rNorm));
            $tri   = self::dice($qTri, $rTri);

            $common = 0;
            foreach (TextNormalizer::tokens($rNorm . ' ' . (string) $row['answer_norm']) as $t) {
                if (isset($qTokenSet[$t])) {
                    $common++;
                }
            }
            $quick = $tri + ($qTokens ? $common / count($qTokens) : 0);
            $pre[] = ['row' => $row, 'quick' => $quick];
        }
        usort($pre, static function ($a, $b) {
            return $b['quick'] <=> $a['quick'];
        });
        $pre = array_slice($pre, 0, max(self::PRESELECT, $topK));

        // مرحله‌ی ۲
        $out = [];
        foreach ($pre as $item) {
            $row = $item['row'];
            $rNorm   = (string) $row['question_norm'];
            $rTokens = TextNormalizer::tokens($rNorm);
            $aTokens = TextNormalizer::tokens((string) $row['answer_norm']);

            $qs = self::questionSimilarity($qNorm, $qTokens, $qTri, $rNorm, $rTokens);
            $coverage = $qTokens ? self::matchCount($qTokens, $aTokens) / count($qTokens) : 0.0;
            $score = max($qs, 0.6 * $coverage + 0.2 * $qs);

            $out[] = ['id' => (int) $row['id'], 'score' => round($score, 4), 'question_score' => round($qs, 4)];
        }
        usort($out, static function ($a, $b) {
            return $b['score'] <=> $a['score'];
        });
        return array_slice($out, 0, $topK);
    }

    /**
     * شباهت دو سوال بین ۰ و ۱.
     * @param string[] $qTokens @param array<string,true> $qTri @param string[] $rTokens
     */
    public static function questionSimilarity(string $qNorm, array $qTokens, array $qTri, string $rNorm, array $rTokens): float
    {
        if (TextNormalizer::compact($qNorm) === TextNormalizer::compact($rNorm)) {
            return 1.0;
        }
        $tri = self::dice($qTri, TextNormalizer::trigrams(TextNormalizer::compact($rNorm)));
        if (!$qTokens || !$rTokens) {
            return $tri;
        }
        $matched = self::matchCount($qTokens, $rTokens);
        $tokenScore = (2 * $matched) / (count($qTokens) + count($rTokens));
        return min(1.0, 0.65 * $tokenScore + 0.35 * $tri);
    }

    /** @param string[] $a @param string[] $b */
    private static function matchCount(array $a, array $b): float
    {
        if (!$a || !$b) {
            return 0.0;
        }
        $bSet = array_flip($b);
        $count = 0.0;
        foreach ($a as $t) {
            $t = (string) $t;
            if (isset($bSet[$t])) {
                $count += 1;
                continue;
            }
            $best = 0.0;
            foreach ($b as $u) {
                $s = self::tokenSimilarity($t, (string) $u);
                if ($s > $best) {
                    $best = $s;
                    if ($best >= 0.9) {
                        break;
                    }
                }
            }
            $count += $best;
        }
        return $count;
    }

    /** شباهت دو کلمه: پسوندها (ها، ی، ش، ...) و یک غلط تایپی */
    private static function tokenSimilarity(string $a, string $b): float
    {
        $la = mb_strlen($a);
        $lb = mb_strlen($b);
        if (abs($la - $lb) > 3) {
            return 0.0;
        }
        $short = $la <= $lb ? $a : $b;
        $long  = $la <= $lb ? $b : $a;
        if (mb_strlen($short) >= 3 && mb_strpos($long, $short) === 0 && mb_strlen($long) - mb_strlen($short) <= 3) {
            return 0.85; // ریشه‌ی مشترک: «قیمت» / «قیمتش»
        }
        if (min($la, $lb) >= 4 && self::levenshtein($a, $b) <= 1) {
            return 0.8; // غلط تایپی
        }
        return 0.0;
    }

    /** @param array<string,true> $a @param array<string,true> $b */
    private static function dice(array $a, array $b): float
    {
        if (!$a || !$b) {
            return 0.0;
        }
        $common = count(array_intersect_key($a, $b));
        return (2 * $common) / (count($a) + count($b));
    }

    private static function levenshtein(string $a, string $b): int
    {
        $x = preg_split('//u', $a, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $y = preg_split('//u', $b, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $lx = count($x);
        $ly = count($y);
        $prev = range(0, $ly);
        for ($i = 1; $i <= $lx; $i++) {
            $cur = [$i];
            for ($j = 1; $j <= $ly; $j++) {
                $cost = $x[$i - 1] === $y[$j - 1] ? 0 : 1;
                $cur[$j] = min($prev[$j] + 1, $cur[$j - 1] + 1, $prev[$j - 1] + $cost);
            }
            $prev = $cur;
        }
        return $prev[$ly];
    }
}
