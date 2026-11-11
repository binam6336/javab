<?php

declare(strict_types=1);

namespace App\Services;

/**
 * یکسان‌سازی متن فارسی برای مقایسه: حروف عربی، اعراب، نیم‌فاصله، ارقام، علائم.
 */
final class TextNormalizer
{
    private const MAP = [
        'ي' => 'ی', 'ى' => 'ی', 'ئ' => 'ی', 'ﻯ' => 'ی', 'ﻰ' => 'ی', 'ﻱ' => 'ی',
        'ك' => 'ک', 'ﻙ' => 'ک', 'ﻚ' => 'ک',
        'ة' => 'ه', 'ۀ' => 'ه', 'ﻩ' => 'ه', 'ﻪ' => 'ه',
        'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا', 'ٲ' => 'ا', 'ٳ' => 'ا',
        'ؤ' => 'و',
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
    ];

    /** کلمات پرتکرار که در شباهت‌سنجی وزنی ندارند */
    private const STOPWORDS = [
        'و', 'در', 'به', 'از', 'که', 'را', 'با', 'این', 'ان', 'است', 'هست', 'هستند', 'بود', 'یک', 'برای', 'تا',
        'یا', 'هم', 'نیز', 'اما', 'ولی', 'اگر', 'چه', 'چی', 'چیه', 'چیست', 'ایا', 'من', 'تو', 'ما', 'شما', 'او',
        'می', 'شود', 'میشود', 'میشه', 'کنم', 'کنید', 'کرد', 'کردن', 'باید', 'بر', 'های', 'ها', 'رو', 'لطفا',
        'سلام', 'ممنون', 'مرسی', 'خیلی', 'دارد', 'داره', 'دارم', 'دارید', 'شده', 'خواهد', 'هر', 'همه', 'ای',
        'the', 'a', 'an', 'is', 'are', 'to', 'of', 'and', 'or', 'in', 'on', 'for', 'what', 'how', 'do', 'does', 'i', 'you',
    ];

    /** متن نرمال‌شده با فاصله‌ی تکی بین کلمات */
    public static function normalize(string $text): string
    {
        $text = str_replace(["\xE2\x80\x8C", "\xE2\x80\x8B", "\xE2\x80\x8D", "\xEF\xBB\xBF", "\xE2\x80\x8E", "\xE2\x80\x8F"], '', $text);
        $text = (string) preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06ED}\x{0640}]/u', '', $text);
        $text = strtr($text, self::MAP);
        $text = mb_strtolower($text, 'UTF-8');
        $text = (string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text);
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    /** بدون فاصله (برای مقایسه‌ی حرف‌به‌حرف) */
    public static function compact(string $normalized): string
    {
        return str_replace(' ', '', $normalized);
    }

    /** @return string[] همه‌ی کلمات */
    public static function words(string $normalized): array
    {
        return $normalized === '' ? [] : explode(' ', $normalized);
    }

    /** @return string[] کلمات معنادار (بدون کلمات پرتکرار)، یکتا */
    public static function tokens(string $normalized): array
    {
        static $stop = null;
        if ($stop === null) {
            $stop = array_flip(self::STOPWORDS);
        }
        $out = [];
        foreach (self::words($normalized) as $w) {
            if (!isset($stop[$w]) && mb_strlen($w) > 1) {
                $out[$w] = true;
            }
        }
        return array_map('strval', array_keys($out));
    }

    /** @return array<string,true> مجموعه‌ی سه‌حرفی‌ها */
    public static function trigrams(string $compact): array
    {
        $chars = preg_split('//u', $compact, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $n = count($chars);
        $out = [];
        if ($n === 0) {
            return $out;
        }
        if ($n < 3) {
            $out[implode('', $chars)] = true;
            return $out;
        }
        for ($i = 0; $i + 3 <= $n; $i++) {
            $out[$chars[$i] . $chars[$i + 1] . $chars[$i + 2]] = true;
        }
        return $out;
    }
}
