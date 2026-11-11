<?php

declare(strict_types=1);

use App\Core\Env;

return [
    'name'     => (string) Env::get('APP_NAME', 'جواب'),
    'debug'    => Env::bool('APP_DEBUG', false),
    'key'      => (string) Env::get('APP_KEY', 'change-me'),
    'timezone' => (string) Env::get('APP_TIMEZONE', 'Asia/Tehran'),

    // ------------------------------------------------------------------
    // هوش مصنوعی (ساختار درخواست مطابق API سازگار با OpenAI chat/completions)
    // ------------------------------------------------------------------
    'ai' => [
        'url'         => (string) Env::get('AI_URL', 'https://apihub.agnes-ai.com/v1/chat/completions'),
        'model'       => (string) Env::get('AI_MODEL', 'agnes-2.5-flash'),
        'api_key'     => (string) Env::get('AI_API_KEY', ''),
        'require_key' => Env::bool('AI_REQUIRE_KEY', true),
        'no_think'    => Env::bool('AI_NO_THINK', false),
        'timeout'     => Env::int('AI_TIMEOUT', 60),
        'temperature' => Env::float('AI_TEMPERATURE', 0.0),
        'top_p'       => Env::float('AI_TOP_P', 0.9),
        'max_tokens'  => Env::int('AI_MAX_TOKENS', 1200),
        'retries'     => Env::int('AI_RETRIES', 1),
        // سقف توکن خروجی برای «تحلیل دانشنامه‌ها»
        'analysis_max_tokens' => Env::int('AI_ANALYSIS_MAX_TOKENS', 3000),
    ],

    // ------------------------------------------------------------------
    // ریت لیمیت
    // ------------------------------------------------------------------
    'rate' => [
        // سقف درخواست به مدل هوش مصنوعی (کل سیستم)
        'ai_per_minute'   => Env::int('AI_RATE_PER_MINUTE', 20),
        'ai_per_day'      => Env::int('AI_RATE_PER_DAY', 2000),
        'ai_concurrent'   => Env::int('AI_MAX_CONCURRENT', 3),
        // اگر مدل 429 داد و Retry-After نفرستاد، این‌قدر صبر می‌کنیم (ثانیه)
        'ai_cooldown'     => Env::int('AI_COOLDOWN_SECONDS', 30),
        // سقف سوال برای هر کاربر (بر اساس IP)
        'user_per_minute' => Env::int('CHAT_RATE_PER_MINUTE', 6),
        'user_per_hour'   => Env::int('CHAT_RATE_PER_HOUR', 60),
    ],

    // ------------------------------------------------------------------
    // دانشنامه و تطابق
    // ------------------------------------------------------------------
    'kb' => [
        // حداقل درصد اطمینان برای ارسال پاسخ
        'min_confidence' => Env::int('MIN_CONFIDENCE', 80),
        // اگر سوال تقریباً عین یکی از سوال‌های دانشنامه باشد، بدون صدا زدن AI پاسخ داده می‌شود
        'direct_match'   => Env::float('KB_DIRECT_MATCH', 0.92),
        // حداقل شباهت لغوی برای این‌که اصلاً سراغ AI برویم (فقط وقتی دانشنامه بزرگ است)
        'min_relevance'  => Env::float('KB_MIN_RELEVANCE', 0.12),
        // اگر تعداد کل موارد دانشنامه کمتر از این باشد، همه‌اش برای AI فرستاده می‌شود
        'send_all_below' => Env::int('KB_SEND_ALL_BELOW', 30),
        // تعداد نزدیک‌ترین موارد که برای AI فرستاده می‌شود
        'top_k'          => Env::int('KB_TOP_K', 12),
        'scan_limit'     => Env::int('KB_SCAN_LIMIT', 20000),
        'history'        => Env::int('CHAT_HISTORY_MESSAGES', 4),
        'cache_ttl'      => Env::int('ANSWER_CACHE_TTL', 86400),
    ],

    'texts' => [
        'no_answer'    => (string) Env::get('NO_ANSWER_MESSAGE', 'متأسفم، برای این سوال پاسخ دقیقی در دانشنامه پیدا نکردم. سوال شما ثبت شد تا پاسخ آن اضافه شود.'),
        'rate_limited' => 'تعداد درخواست‌ها زیاد است؛ لطفاً {sec} ثانیه دیگر دوباره امتحان کنید.',
        'ai_busy'      => 'سرویس هوش مصنوعی در حال حاضر شلوغ است؛ لطفاً {sec} ثانیه دیگر دوباره امتحان کنید.',
        'ai_error'     => 'ارتباط با سرویس هوش مصنوعی برقرار نشد؛ لطفاً کمی بعد دوباره امتحان کنید.',
    ],
];
