<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\App;
use App\Core\Database;
use App\Core\Logger;
use App\Models\KbIssue;
use App\Models\Setting;

/**
 * تحلیل معنایی محتوای دانشنامه با AI برای پیدا کردن موارد مستعد پاسخ اشتباه:
 * چندمعنایی، ابهام، تناقض و هم‌پوشانی.
 *
 * - موارد در چند «بسته» به AI داده می‌شوند. وقتی دانشنامه کوچک است همه در یک بسته‌اند.
 *   وقتی بزرگ است، موارد هم‌موضوع (کلمات مشترک) کنار هم قرار می‌گیرند تا AI بتواند آن‌ها را
 *   با هم مقایسه کند. شباهت لغوی فقط برای چیدن بسته‌هاست؛ تشخیص مشکل کاملاً با AI است.
 * - هر هشدار در سرور بررسی می‌شود: متن مشکل‌دار باید عیناً در دانشنامه وجود داشته باشد،
 *   تناقض/هم‌پوشانی باید مورد مرتبط و متن آن را داشته باشد، و اطمینان مدل کافی باشد.
 * - اجرا مرحله‌به‌مرحله است (هر درخواست یک بسته) تا با محدودیت زمان PHP و ریت لیمیت AI سازگار باشد.
 */
final class KbAnalyzer
{
    private const JOB_KEY  = 'kb_analysis_job';
    private const LAST_KEY = 'kb_analysis_last';
    private const LOCK     = 'javab_kb_analysis';

    /** حداکثر تعداد مورد در هر بسته‌ی تحلیل */
    private const BATCH_SIZE = 30;
    /** حداکثر طول پاسخ هر مورد که برای AI فرستاده می‌شود */
    private const ANSWER_CHARS = 1200;
    /** حداقل اطمینان مدل برای ثبت هشدار */
    private const MIN_CONFIDENCE = 80;

    // ---------------------------------------------------------------
    // آمار استفاده
    // ---------------------------------------------------------------

    /** @return array{rows:array<int,array{id:int,question:string,hits:int}>,total_hits:int,used:int,total:int} */
    public static function usage(): array
    {
        $rows = array_map(static function ($r) {
            return ['id' => (int) $r['id'], 'question' => (string) $r['question'], 'hits' => (int) $r['hits']];
        }, Database::all('SELECT `id`, `question`, `hits` FROM `knowledge` ORDER BY `hits` DESC, `id` ASC'));

        $used = array_values(array_filter($rows, static function ($r) {
            return $r['hits'] > 0;
        }));
        return [
            'rows'       => $used,
            'total_hits' => (int) array_sum(array_column($used, 'hits')),
            'used'       => count($used),
            'total'      => count($rows),
        ];
    }

    // ---------------------------------------------------------------
    // وضعیت
    // ---------------------------------------------------------------

    public static function contentCrc(string $question, string $answer): int
    {
        return (int) sprintf('%u', crc32($question . "\n" . $answer));
    }

    /** امضای محتوای کل دانشنامه (برای فهمیدن این‌که تحلیل قدیمی شده یا نه) */
    public static function signature(): string
    {
        $r = Database::one('SELECT COUNT(*) AS c, COALESCE(SUM(CRC32(CONCAT(`id`, 0x0a, `question`, 0x0a, `answer`))), 0) AS s FROM `knowledge`');
        return ($r['c'] ?? 0) . ':' . ($r['s'] ?? 0);
    }

    /** @return array<string,mixed>|null */
    public static function job(): ?array
    {
        $raw = Setting::get(self::JOB_KEY);
        $d = $raw ? json_decode($raw, true) : null;
        return is_array($d) ? $d : null;
    }

    // ---------------------------------------------------------------
    // اجرا
    // ---------------------------------------------------------------

    /** شروع تحلیل تازه @return array{ok:bool,message?:string,total?:int} */
    public static function start(): array
    {
        if (!Database::lock(self::LOCK, 3)) {
            return ['ok' => false, 'message' => 'تحلیل دیگری در حال اجراست؛ کمی بعد دوباره امتحان کنید.'];
        }
        try {
            $items = Database::all('SELECT `id`, `question`, `answer`, `question_norm`, `answer_norm` FROM `knowledge` ORDER BY `id`');
            if (count($items) === 0) {
                return ['ok' => false, 'message' => 'دانشنامه خالی است.'];
            }
            // نتایج نیمه‌کاره‌ی تحلیل قبلی که تمام نشده بود
            $old = self::job();
            if ($old && ($old['run'] ?? '') !== KbIssue::lastRunId()) {
                KbIssue::deleteRun((string) $old['run']);
            }
            $batches = self::plan($items);
            $job = [
                'run'     => bin2hex(random_bytes(8)),
                'batches' => $batches,
                'step'    => 0,
                'failed'  => 0,
                'items'   => count($items),
                'started' => date('c'),
            ];
            Setting::set(self::JOB_KEY, json_encode($job));
            return ['ok' => true, 'total' => count($batches)];
        } finally {
            Database::unlock(self::LOCK);
        }
    }

    /**
     * تحلیل بسته‌ی بعدی.
     * @return array{ok:bool,message?:string,done?:bool,step?:int,total?:int,wait?:int}
     */
    public static function step(): array
    {
        if (!Database::lock(self::LOCK, 3)) {
            return ['ok' => true, 'done' => false, 'wait' => 3];
        }
        try {
            $job = self::job();
            if (!$job || !isset($job['batches'])) {
                return ['ok' => false, 'message' => 'تحلیلی در حال اجرا نیست.'];
            }
            $total = count($job['batches']);
            if ($job['step'] >= $total) {
                return ['ok' => true, 'done' => true, 'step' => $total, 'total' => $total];
            }

            $ids = $job['batches'][$job['step']];
            $res = self::analyzeBatch((string) $job['run'], array_map('intval', $ids));
            if ($res['status'] === 'wait') {
                return ['ok' => true, 'done' => false, 'wait' => $res['wait'], 'step' => $job['step'], 'total' => $total];
            }
            if ($res['status'] === 'fatal') {
                Setting::set(self::JOB_KEY, null);
                KbIssue::deleteRun((string) $job['run']);
                return ['ok' => false, 'message' => $res['message']];
            }
            if ($res['status'] === 'error') {
                $job['failed']++;
            }
            $job['step']++;

            if ($job['step'] >= $total) {
                if ($job['failed'] >= $total) {
                    // هیچ بسته‌ای تحلیل نشد؛ نتیجه‌ی قبلی دست نمی‌خورد
                    Setting::set(self::JOB_KEY, null);
                    KbIssue::deleteRun((string) $job['run']);
                    return ['ok' => false, 'message' => 'تحلیل انجام نشد: ارتباط با سرویس هوش مصنوعی برقرار نشد. جزئیات در storage/logs/http-ai.log است.'];
                }
                KbIssue::keepOnly((string) $job['run']);
                Setting::set(self::LAST_KEY, json_encode([
                    'run'       => $job['run'],
                    'finished'  => date('Y-m-d H:i:s'),
                    'items'     => $job['items'],
                    'batches'   => $total,
                    'failed'    => $job['failed'],
                    'signature' => self::signature(),
                ]));
                Setting::set(self::JOB_KEY, null);
                return ['ok' => true, 'done' => true, 'step' => $total, 'total' => $total, 'failed' => $job['failed']];
            }
            Setting::set(self::JOB_KEY, json_encode($job));
            return ['ok' => true, 'done' => false, 'step' => $job['step'], 'total' => $total];
        } finally {
            Database::unlock(self::LOCK);
        }
    }

    // ---------------------------------------------------------------
    // چیدن بسته‌ها
    // ---------------------------------------------------------------

    /**
     * @param array<int,array<string,mixed>> $items
     * @return array<int,int[]>
     */
    private static function plan(array $items): array
    {
        $ids = array_map(static function ($r) {
            return (int) $r['id'];
        }, $items);
        if (count($ids) <= self::BATCH_SIZE) {
            return [$ids];
        }

        // نمایه‌ی معکوس کلمات معنادار (سوال + پاسخ)
        $tokens = [];
        $index = [];
        foreach ($items as $r) {
            $id = (int) $r['id'];
            $t = TextNormalizer::tokens((string) $r['question_norm'] . ' ' . (string) $r['answer_norm']);
            $tokens[$id] = $t;
            foreach ($t as $w) {
                $index[$w][] = $id;
            }
        }
        // کلمات خیلی پرتکرار برای هم‌گروه کردن بی‌فایده‌اند
        $maxDf = max(5, (int) (count($ids) * 0.08));

        $neighbors = [];
        foreach ($tokens as $id => $t) {
            $shared = [];
            foreach ($t as $w) {
                if (count($index[$w]) > $maxDf) {
                    continue;
                }
                foreach ($index[$w] as $other) {
                    if ($other !== $id) {
                        $shared[$other] = ($shared[$other] ?? 0) + 1;
                    }
                }
            }
            $scored = [];
            foreach ($shared as $other => $n) {
                $den = min(count($t), count($tokens[$other]));
                $score = $den > 0 ? $n / $den : 0;
                if ($n >= 2 && $score >= 0.25) {
                    $scored[$other] = $score;
                }
            }
            arsort($scored);
            $neighbors[$id] = array_slice(array_keys($scored), 0, 6);
        }

        // هر بسته از یک مورد شروع می‌شود و نزدیک‌ترین‌هایش را (به ترتیب پیمایش) جمع می‌کند
        $placed = [];
        $batches = [];
        $current = [];
        foreach ($ids as $seed) {
            if (isset($placed[$seed])) {
                continue;
            }
            $queue = [$seed];
            while ($queue) {
                $id = array_shift($queue);
                if (isset($placed[$id])) {
                    continue;
                }
                if (count($current) >= self::BATCH_SIZE) {
                    $batches[] = $current;
                    $current = [];
                }
                $current[] = $id;
                $placed[$id] = true;
                foreach ($neighbors[$id] ?? [] as $n) {
                    if (!isset($placed[$n])) {
                        $queue[] = $n;
                    }
                }
            }
        }
        if ($current) {
            $batches[] = $current;
        }
        return $batches;
    }

    // ---------------------------------------------------------------
    // تحلیل یک بسته با AI
    // ---------------------------------------------------------------

    /**
     * @param int[] $ids
     * @return array{status:string,wait?:int,message?:string}
     */
    private static function analyzeBatch(string $run, array $ids): array
    {
        if (!$ids) {
            return ['status' => 'ok'];
        }
        $in = implode(',', array_fill(0, count($ids), '?'));
        $rows = [];
        foreach (Database::all("SELECT `id`, `question`, `answer` FROM `knowledge` WHERE `id` IN ({$in})", $ids) as $r) {
            $rows[(int) $r['id']] = $r;
        }
        if (!$rows) {
            return ['status' => 'ok']; // موارد این بسته در این فاصله حذف شده‌اند
        }

        $payload = [];
        foreach ($rows as $id => $r) {
            $payload[] = ['id' => $id, 'q' => (string) $r['question'], 'a' => mb_substr((string) $r['answer'], 0, self::ANSWER_CHARS)];
        }
        $messages = [
            ['role' => 'system', 'content' => self::prompt()],
            ['role' => 'user', 'content' => json_encode(['items' => $payload], JSON_UNESCAPED_UNICODE)],
        ];

        $limit = RateLimiter::aiAttempt();
        if (!$limit['ok']) {
            return ['status' => 'wait', 'wait' => max(1, $limit['retry_after'])];
        }
        $slot = RateLimiter::acquireAiSlot(3);
        if ($slot === null) {
            return ['status' => 'wait', 'wait' => 3];
        }
        try {
            $res = AiClient::chat($messages, [
                'max_tokens'  => (int) App::config('ai.analysis_max_tokens', 3000),
                'temperature' => 0.0,
                'timeout'     => max(60, (int) App::config('ai.timeout', 60)),
            ]);
        } finally {
            RateLimiter::releaseAiSlot($slot);
        }

        if (!$res['ok']) {
            if ($res['rate_limited']) {
                $wait = $res['retry_after'] > 0 ? $res['retry_after'] : (int) App::config('rate.ai_cooldown', 30);
                RateLimiter::aiCooldown($wait);
                return ['status' => 'wait', 'wait' => $wait];
            }
            if ($res['code'] === 401 || $res['code'] === 403) {
                return ['status' => 'fatal', 'message' => 'سرویس هوش مصنوعی درخواست را رد کرد (کد ' . $res['code'] . '). کلید API را در فایل .env بررسی کنید.'];
            }
            return ['status' => 'error'];
        }

        $data = AiClient::extractJson($res['content']);
        if ($data === null || !isset($data['issues']) || !is_array($data['issues'])) {
            Logger::warning('تحلیل دانشنامه: JSON معتبر از مدل دریافت نشد', ['raw' => mb_substr($res['content'], 0, 300)]);
            AiClient::logHttp('تحلیل دانشنامه: JSON معتبر از مدل دریافت نشد', $res['trace']);
            return ['status' => 'error'];
        }

        $issues = self::validate($data['issues'], $rows);
        KbIssue::insertMany($run, $issues);
        Logger::info('تحلیل دانشنامه: بسته', ['items' => count($rows), 'reported' => count($data['issues']), 'accepted' => count($issues)]);
        return ['status' => 'ok'];
    }

    /**
     * فقط هشدارهایی که شواهد قابل‌بررسی دارند پذیرفته می‌شوند.
     * @param array<int,mixed> $raw
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    private static function validate(array $raw, array $rows): array
    {
        $out = [];
        $seenPairs = [];
        foreach ($raw as $it) {
            if (!is_array($it)) {
                continue;
            }
            $id = (int) ($it['id'] ?? 0);
            $type = (string) ($it['type'] ?? '');
            $reason = trim((string) ($it['reason'] ?? ''));
            $excerpt = trim((string) ($it['excerpt'] ?? ''));
            $confidence = (int) ($it['confidence'] ?? 0);

            if (!isset($rows[$id]) || !isset(KbIssue::TYPES[$type]) || $confidence < self::MIN_CONFIDENCE || mb_strlen($reason) < 15) {
                continue;
            }
            if (!self::quoted($excerpt, $rows[$id])) {
                continue; // متن ذکرشده در خود دانشنامه نیست → هشدار بی‌پشتوانه
            }

            $related = [];
            foreach ((array) ($it['related'] ?? []) as $r) {
                $r = (int) $r;
                if ($r !== $id && isset($rows[$r]) && !in_array($r, $related, true)) {
                    $related[] = $r;
                }
            }
            $relatedExcerpt = trim((string) ($it['related_excerpt'] ?? ''));

            if ($type === 'contradiction' || $type === 'overlap') {
                if (!$related) {
                    continue;
                }
                $found = false;
                foreach ($related as $r) {
                    if (self::quoted($relatedExcerpt, $rows[$r])) {
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    continue;
                }
                $pair = $type . ':' . min($id, $related[0]) . '-' . max($id, $related[0]);
                if (isset($seenPairs[$pair])) {
                    continue; // همان تناقض از سمت مورد دیگر
                }
                $seenPairs[$pair] = true;
            } else {
                $relatedExcerpt = '';
            }

            $out[] = [
                'knowledge_id'    => $id,
                'type'            => $type,
                'reason'          => mb_substr($reason, 0, 1000),
                'related'         => array_slice($related, 0, 10),
                'excerpt'         => mb_substr($excerpt, 0, 600),
                'related_excerpt' => $relatedExcerpt !== '' ? mb_substr($relatedExcerpt, 0, 600) : null,
                'content_crc'     => self::contentCrc((string) $rows[$id]['question'], (string) $rows[$id]['answer']),
                'related_crc'     => $related ? self::contentCrc((string) $rows[$related[0]]['question'], (string) $rows[$related[0]]['answer']) : 0,
            ];
        }
        return $out;
    }

    /** آیا این متن (با چشم‌پوشی از تفاوت‌های نوشتاری) واقعاً در سوال یا پاسخ این مورد آمده است؟ @param array<string,mixed> $row */
    private static function quoted(string $excerpt, array $row): bool
    {
        $e = TextNormalizer::normalize($excerpt);
        if (mb_strlen($e) < 2) {
            return false;
        }
        $hay = ' ' . TextNormalizer::normalize((string) $row['question']) . ' | ' . TextNormalizer::normalize((string) $row['answer']) . ' ';
        return mb_strpos($hay, $e) !== false;
    }

    private static function prompt(): string
    {
        $min = self::MIN_CONFIDENCE;
        return <<<EOT
تو بازبین کیفیت محتوای «دانشنامه»ی یک سیستم پاسخ خودکار هستی. این سیستم سوال کاربران را فقط بر اساس همین موارد جواب می‌دهد.
وظیفه: مواردی را پیدا کن که «واقعاً» ممکن است باعث پاسخ اشتباه یا گمراه‌کننده شوند.

## ورودی
{"items":[{"id":شماره,"q":"سوال","a":"پاسخ"}, ...]}

## انواع مشکل (فقط همین چهار نوع)
- ambiguity (چندمعنایی): عبارتی در سوال یا پاسخ که دو یا چند برداشت معقول و متفاوت دارد و این برداشت‌ها به نتیجه‌ی متفاوت برای کاربر می‌رسند. باید بتوانی دو برداشت مشخص را نام ببری.
- vague (ابهام در توضیح یا دستورالعمل): پاسخ برای عمل کردن یا فهمیدن کافی نیست؛ مثلاً مرحله‌ی لازم جا افتاده، به چیزی نامشخص ارجاع داده («بخش مربوطه»)، یا شرط/عدد/مهلت ضروری بیان نشده. فقط وقتی که کاربر با همین متن واقعاً گمراه یا سردرگم می‌شود.
- contradiction (تناقض): دو مورد درباره‌ی «همان موضوع» و «همان شرایط» چیزی می‌گویند که هم‌زمان نمی‌تواند درست باشد (مثل دو عدد، دو مهلت، دو قاعده‌ی متفاوت برای یک چیز).
- overlap (هم‌پوشانی): دو مورد عملاً به «یک سوال با یک نیت» جواب می‌دهند ولی پاسخ‌هایشان در جزئیات یا دامنه فرق دارد، طوری که سیستم ممکن است برای یک سوال، گاهی این و گاهی آن را برگرداند و کاربر پاسخ ناقص یا متفاوت بگیرد.

## قوانین سخت‌گیرانه
1. تحلیل معنایی کن. شباهت موضوع یا کلمات مشترک به‌تنهایی تناقض یا هم‌پوشانی نیست. دو مورد درباره‌ی موضوع مشابه ولی سوال متفاوت (مثلاً هزینه‌ی ارسال و زمان ارسال) هم‌پوشانی ندارند.
2. اگر تفاوت دو مورد با شرایط متفاوت توضیح داده می‌شود (شهر، نوع کاربر، تاریخ، نوع محصول و ...)، تناقض نیست.
3. اطلاعات کم ولی کافی، یا پاسخ کوتاه ولی روشن، ابهام نیست. سلیقه‌ی نگارشی، غلط املایی و پیشنهاد بهبود، مشکل نیست.
4. excerpt باید «عیناً» بخشی از متن q یا a همان مورد باشد (کپی دقیق، کوتاه و مشخص؛ همان بخشی که مشکل از آن ناشی می‌شود).
5. برای contradiction و overlap: related شماره‌ی مورد(های) دیگر، و related_excerpt متن عیناً کپی‌شده از مورد مرتبط است. هر جفت را فقط یک بار گزارش کن.
6. reason دقیق و مستند باشد: برای چندمعنایی دو برداشت را بنویس؛ برای ابهام بگو دقیقاً چه چیزی کم است؛ برای تناقض بگو کدام ادعا با کدام ادعا ناسازگار است؛ برای هم‌پوشانی بگو چرا نیت سوال یکی است و پاسخ‌ها چه فرقی دارند.
7. confidence (0 تا 100): چقدر مطمئنی که این واقعاً مشکل است. فقط موارد با confidence حداقل {$min} را گزارش کن. در صورت تردید گزارش نکن؛ هشدار غیرواقعی از هشدار نداده بدتر است.
8. اگر مشکلی نیست: {"issues":[]}
9. متن reason فارسی و حداکثر سه جمله باشد.

## خروجی
فقط یک JSON معتبر بدون هیچ متن اضافه:
{"issues":[{"id":1,"type":"contradiction","related":[2],"excerpt":"...","related_excerpt":"...","reason":"...","confidence":90}]}
EOT;
    }
}
