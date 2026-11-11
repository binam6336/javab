<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\App;
use App\Core\Database;
use App\Core\Logger;
use App\Models\ChatMessage;
use App\Models\Knowledge;
use App\Models\Setting;
use App\Models\Unanswered;

/**
 * فلوی اصلی پاسخ خودکار:
 *  ۱) ریت لیمیت کاربر
 *  ۲) پیدا کردن نزدیک‌ترین موارد دانشنامه (Retriever)
 *  ۳) تطابق تقریباً کامل → پاسخ مستقیم از دانشنامه (بدون مصرف سهمیه‌ی AI)
 *  ۴) کش پاسخ
 *  ۵) ریت لیمیت/هم‌زمانی مدل AI → ارسال درخواست
 *  ۶) فقط اگر اطمینان ≥ MIN_CONFIDENCE بود پاسخ ارسال می‌شود؛ وگرنه سوال در «بی‌پاسخ‌ها» ثبت می‌شود.
 */
final class AnswerService
{
    /**
     * @return array{ok:bool,status:string,message:string,confidence:?int,sources:array<int,array{id:int,question:string}>,via:?string,retry_after:int}
     */
    public static function ask(string $conversationId, string $question, string $ip): array
    {
        $question = trim((string) preg_replace('/\s+/u', ' ', $question));
        $len = mb_strlen($question);
        if ($len < 2) {
            return self::fail('سوال خیلی کوتاه است.');
        }
        if ($len > Knowledge::QUESTION_MAX) {
            return self::fail('سوال حداکثر می‌تواند ' . Knowledge::QUESTION_MAX . ' کاراکتر باشد.');
        }

        // ۱) ریت لیمیت کاربر
        $limit = RateLimiter::attempt('chat', $ip, [
            [(int) App::config('rate.user_per_minute', 6), 60],
            [(int) App::config('rate.user_per_hour', 60), 3600],
        ]);
        if (!$limit['ok']) {
            return self::result('rate_limited', self::text('rate_limited', $limit['retry_after']), null, [], null, $limit['retry_after']);
        }

        $ipHash = hash('sha256', (string) App::config('key') . '|' . $ip);
        $history = ChatMessage::recentContext($conversationId, (int) App::config('kb.history', 4));
        ChatMessage::add($conversationId, 'user', $question, ['ip_hash' => $ipHash]);

        $result = self::resolve($question, $history);

        // ذخیره‌ی پاسخ در گفتگو (پیام خطا/شلوغی ذخیره نمی‌شود تا کاربر دوباره بپرسد)
        if (in_array($result['status'], ['answered', 'no_answer'], true)) {
            ChatMessage::add($conversationId, 'assistant', $result['message'], [
                'status'     => $result['status'],
                'confidence' => $result['confidence'],
                'sources'    => array_column($result['sources'], 'id'),
                'via'        => $result['via'],
            ]);
        }
        if ($result['status'] === 'no_answer') {
            Unanswered::record($question, $result['confidence']);
        }
        if ($result['status'] === 'answered') {
            Knowledge::addHits(array_column($result['sources'], 'id'));
        }
        return $result;
    }

    /**
     * @param array<int,array{role:string,content:string}> $history
     * @return array{ok:bool,status:string,message:string,confidence:?int,sources:array<int,array{id:int,question:string}>,via:?string,retry_after:int}
     */
    private static function resolve(string $question, array $history): array
    {
        $kb = (array) App::config('kb');
        $minConfidence = (int) $kb['min_confidence'];
        $total = Knowledge::count();

        if ($total === 0) {
            return self::noAnswer(null, 'empty');
        }

        // ۲) نزدیک‌ترین موارد
        $sendAll = $total <= (int) $kb['send_all_below'];
        $topK = $sendAll ? $total : (int) $kb['top_k'];
        $hits = Retriever::search($question, $topK, (int) $kb['scan_limit']);
        $best = $hits[0] ?? null;

        // ۳) تطابق تقریباً کامل با یک سوال موجود
        if ($best && $best['question_score'] >= (float) $kb['direct_match']) {
            $row = Knowledge::find($best['id']);
            if ($row) {
                $conf = (int) round($best['question_score'] * 100);
                if ($conf >= $minConfidence) {
                    return self::result('answered', (string) $row['answer'], $conf, [self::source($row)], 'direct');
                }
            }
        }

        // دانشنامه بزرگ است و هیچ مورد نزدیکی پیدا نشد → AI صدا زده نمی‌شود
        if (!$sendAll && (!$best || $best['score'] < (float) $kb['min_relevance'])) {
            return self::noAnswer($best ? (int) round($best['score'] * 100) : 0, 'retrieval');
        }

        // ۴) کش (فقط سوال‌های مستقل، بدون تاریخچه)
        $cacheKey = hash('sha256', TextNormalizer::compact(TextNormalizer::normalize($question)));
        $revision = Setting::kbRevision();
        if (!$history) {
            $cached = self::cacheGet($cacheKey, $revision, (int) $kb['cache_ttl']);
            if ($cached) {
                return $cached;
            }
        }

        $candidates = Knowledge::findMany(array_column($hits, 'id'));
        // ترتیب بر اساس امتیاز
        $ordered = [];
        foreach ($hits as $h) {
            if (isset($candidates[$h['id']])) {
                $ordered[] = $candidates[$h['id']];
            }
        }

        // ۵) ریت لیمیت و هم‌زمانی مدل
        $ai = self::callAi($question, $history, $ordered);
        if ($ai['status'] !== 'ok') {
            return $ai['result'];
        }

        // ۶) ارزیابی پاسخ مدل
        $data = $ai['data'];
        $confidence = max(0, min(100, (int) ($data['confidence'] ?? 0)));
        $answer = trim((string) preg_replace('/<think>.*?<\/think>/s', '', (string) ($data['answer'] ?? '')));
        $sourceIds = array_values(array_filter(array_map('intval', (array) ($data['sources'] ?? [])), static function ($id) use ($candidates) {
            return isset($candidates[$id]);
        }));

        if ($answer === '' || !$sourceIds || $confidence < $minConfidence || !empty($data['no_answer'])) {
            $result = self::noAnswer($confidence, 'ai');
        } else {
            $sources = array_map(static function ($id) use ($candidates) {
                return self::source($candidates[$id]);
            }, array_slice($sourceIds, 0, 3));
            $result = self::result('answered', $answer, $confidence, $sources, 'ai');
        }

        if (!$history) {
            self::cachePut($cacheKey, $revision, $result);
        }
        return $result;
    }

    /**
     * @param array<int,array{role:string,content:string}> $history
     * @param array<int,array<string,mixed>> $candidates
     * @return array{status:string,data?:array<string,mixed>,result?:array}
     */
    private static function callAi(string $question, array $history, array $candidates): array
    {
        $retries = max(0, (int) App::config('ai.retries', 1));
        $messages = [
            ['role' => 'system', 'content' => self::systemPrompt((int) App::config('kb.min_confidence', 80))],
            ['role' => 'user', 'content' => self::userPrompt($question, $history, $candidates)],
        ];

        $slot = RateLimiter::acquireAiSlot();
        if ($slot === null) {
            return ['status' => 'busy', 'result' => self::result('rate_limited', self::text('ai_busy', 5), null, [], null, 5)];
        }

        try {
            for ($attempt = 0; $attempt <= $retries; $attempt++) {
                $limit = RateLimiter::aiAttempt();
                if (!$limit['ok']) {
                    return ['status' => 'limited', 'result' => self::result('rate_limited', self::text('ai_busy', $limit['retry_after']), null, [], null, $limit['retry_after'])];
                }

                $res = AiClient::chat($messages);

                if ($res['ok']) {
                    $data = AiClient::extractJson($res['content']);
                    if ($data !== null) {
                        return ['status' => 'ok', 'data' => $data];
                    }
                    Logger::warning('JSON از پاسخ مدل استخراج نشد', ['raw' => mb_substr($res['content'], 0, 300)]);
                    AiClient::logHttp('JSON از پاسخ مدل استخراج نشد', $res['trace']);
                    continue; // یک بار دیگر
                }

                if ($res['rate_limited']) {
                    $wait = $res['retry_after'] > 0 ? $res['retry_after'] : (int) App::config('rate.ai_cooldown', 30);
                    RateLimiter::aiCooldown($wait);
                    if ($wait <= 3 && $attempt < $retries) {
                        sleep($wait);
                        continue;
                    }
                    return ['status' => 'limited', 'result' => self::result('rate_limited', self::text('ai_busy', $wait), null, [], null, $wait)];
                }

                // خطای موقت سرور/شبکه → یک بار دیگر با کمی مکث
                if ($attempt < $retries && ($res['code'] === 0 || $res['code'] >= 500)) {
                    usleep(800000 * ($attempt + 1));
                    continue;
                }
                break;
            }
        } finally {
            RateLimiter::releaseAiSlot($slot);
        }

        return ['status' => 'error', 'result' => self::result('error', self::text('ai_error', 0), null, [], null, 0)];
    }

    private static function systemPrompt(int $minConfidence): string
    {
        return <<<EOT
تو «دستیار پاسخ‌گوی خودکار» هستی و فقط بر اساس «دانشنامه»ی داده‌شده پاسخ می‌دهی.

## ورودی
یک JSON با این کلیدها:
- knowledge: آرایه‌ای از {"id":شماره,"q":"سوال","a":"پاسخ"} — تنها منبع مجاز اطلاعات.
- history: چند پیام قبلی گفتگو (فقط برای فهمیدن منظور سوال‌های ادامه‌دار مثل «قیمتش چنده؟»).
- question: سوال فعلی کاربر.

## قوانین
1. فقط از اطلاعات knowledge استفاده کن. هیچ اطلاعاتی از خودت اضافه، حدس یا اختراع نکن.
2. اگر سوال با یک یا چند مورد knowledge هم‌معنی است (حتی با کلمات متفاوت، غلط تایپی، محاوره‌ای یا فینگلیش)، پاسخ را بر اساس «a» همان موارد بنویس.
3. پاسخ را روان، مؤدبانه و به زبان سوال کاربر بنویس. می‌توانی چند مورد را ترکیب کنی، اما معنا را تغییر نده.
4. confidence عددی بین 0 تا 100 است: چقدر مطمئنی که پاسخ تو «کاملاً» با اطلاعات knowledge پشتیبانی می‌شود و دقیقاً به همان سوال جواب می‌دهد.
   - اگر knowledge فقط موضوع مشابهی دارد ولی به خود سوال جواب نمی‌دهد → confidence زیر {$minConfidence}.
   - اگر برای پاسخ مجبوری چیزی حدس بزنی → confidence زیر {$minConfidence}.
   - اگر هیچ مورد مرتبطی نیست → confidence = 0 و answer خالی.
5. sources: شماره‌ی id مواردی که پاسخ از آن‌ها گرفته شده.

## خروجی
فقط یک JSON معتبر، بدون هیچ توضیح یا متن اضافه:
{"answer":"متن پاسخ","confidence":0,"sources":[1,2]}
EOT;
    }

    /**
     * @param array<int,array{role:string,content:string}> $history
     * @param array<int,array<string,mixed>> $candidates
     */
    private static function userPrompt(string $question, array $history, array $candidates): string
    {
        $knowledge = array_map(static function ($row) {
            return ['id' => (int) $row['id'], 'q' => (string) $row['question'], 'a' => mb_substr((string) $row['answer'], 0, 1500)];
        }, $candidates);

        return json_encode([
            'knowledge' => $knowledge,
            'history'   => $history,
            'question'  => $question,
        ], JSON_UNESCAPED_UNICODE) . (App::config('ai.no_think') ? "\n/no_think" : '');
    }

    // ---------------------------------------------------------------
    // کش
    // ---------------------------------------------------------------

    private static function cacheGet(string $key, string $revision, int $ttl): ?array
    {
        $row = Database::one(
            'SELECT * FROM `answer_cache` WHERE `question_hash` = ? AND `kb_revision` = ? AND `created_at` > NOW() - INTERVAL ? SECOND',
            [$key, $revision, $ttl]
        );
        if (!$row) {
            return null;
        }
        if ($row['status'] === 'no_answer') {
            return self::noAnswer($row['confidence'] !== null ? (int) $row['confidence'] : null, 'cache');
        }
        $ids = array_filter(array_map('intval', explode(',', (string) $row['sources'])));
        $rows = Knowledge::findMany($ids);
        if (count($rows) !== count($ids)) {
            return null;
        }
        $sources = array_map([self::class, 'source'], array_values($rows));
        return self::result('answered', (string) $row['answer'], (int) $row['confidence'], $sources, 'cache');
    }

    private static function cachePut(string $key, string $revision, array $result): void
    {
        Database::run(
            'REPLACE INTO `answer_cache` (`question_hash`, `kb_revision`, `status`, `answer`, `confidence`, `sources`, `created_at`)
             VALUES (?, ?, ?, ?, ?, ?, NOW())',
            [
                $key,
                $revision,
                $result['status'],
                $result['status'] === 'answered' ? $result['message'] : null,
                $result['confidence'],
                implode(',', array_column($result['sources'], 'id')) ?: null,
            ]
        );
    }

    // ---------------------------------------------------------------
    // سازنده‌های خروجی
    // ---------------------------------------------------------------

    /** @param array<string,mixed> $row @return array{id:int,question:string} */
    private static function source(array $row): array
    {
        return ['id' => (int) $row['id'], 'question' => (string) $row['question']];
    }

    private static function noAnswer(?int $confidence, string $via): array
    {
        return self::result('no_answer', (string) App::config('texts.no_answer'), $confidence, [], $via);
    }

    private static function text(string $key, int $seconds): string
    {
        return str_replace('{sec}', fa_num(max(1, $seconds)), (string) App::config('texts.' . $key));
    }

    private static function fail(string $message): array
    {
        return ['ok' => false, 'status' => 'invalid', 'message' => $message, 'confidence' => null, 'sources' => [], 'via' => null, 'retry_after' => 0];
    }

    /** @param array<int,array{id:int,question:string}> $sources */
    private static function result(string $status, string $message, ?int $confidence, array $sources, ?string $via, int $retryAfter = 0): array
    {
        return [
            'ok'          => in_array($status, ['answered', 'no_answer'], true),
            'status'      => $status,
            'message'     => $message,
            'confidence'  => $confidence,
            'sources'     => $sources,
            'via'         => $via,
            'retry_after' => $retryAfter,
        ];
    }
}
