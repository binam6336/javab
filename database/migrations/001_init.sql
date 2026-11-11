-- دانشنامه: هر ردیف یک پرسش و پاسخ
CREATE TABLE IF NOT EXISTS `knowledge` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `question`      TEXT NOT NULL,
    `answer`        TEXT NOT NULL,
    `question_norm` TEXT NOT NULL,
    `answer_norm`   TEXT NOT NULL,
    `hits`          INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_knowledge_updated` (`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- پیام‌های گفتگو (کاربر و دستیار)
CREATE TABLE IF NOT EXISTS `chat_messages` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `conversation_id` CHAR(32) NOT NULL,
    `role`            ENUM('user','assistant') NOT NULL,
    `content`         TEXT NOT NULL,
    `status`          VARCHAR(20) NOT NULL DEFAULT 'ok',
    `confidence`      TINYINT UNSIGNED NULL,
    `sources`         VARCHAR(255) NULL,
    `via`             VARCHAR(20) NULL,
    `ip_hash`         CHAR(64) NULL,
    `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_chat_conv` (`conversation_id`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- سوال‌هایی که پاسخ ۸۰٪ به بالا نداشتند (برای تکمیل دانشنامه)
CREATE TABLE IF NOT EXISTS `unanswered` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `question_hash` CHAR(64) NOT NULL,
    `question`      TEXT NOT NULL,
    `asks`          INT UNSIGNED NOT NULL DEFAULT 1,
    `best_confidence` TINYINT UNSIGNED NULL,
    `last_asked_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `resolved_at`   DATETIME NULL,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_unanswered_hash` (`question_hash`),
    KEY `idx_unanswered_open` (`resolved_at`, `last_asked_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- رویدادهای ریت لیمیت (درخواست‌های کاربر و درخواست‌های ارسالی به مدل)
CREATE TABLE IF NOT EXISTS `rate_events` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `scope`      VARCHAR(30) NOT NULL,
    `key_hash`   CHAR(64) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_rate_lookup` (`scope`, `key_hash`, `created_at`),
    KEY `idx_rate_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- کش پاسخ‌ها برای کم کردن تعداد درخواست به مدل
CREATE TABLE IF NOT EXISTS `answer_cache` (
    `question_hash` CHAR(64) NOT NULL,
    `kb_revision`   VARCHAR(40) NOT NULL,
    `status`        VARCHAR(20) NOT NULL,
    `answer`        TEXT NULL,
    `confidence`    TINYINT UNSIGNED NULL,
    `sources`       VARCHAR(255) NULL,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`question_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- تنظیمات و وضعیت‌های سیستمی (نسخه‌ی دانشنامه، زمان استراحت مدل بعد از 429 و ...)
CREATE TABLE IF NOT EXISTS `settings` (
    `key`        VARCHAR(100) NOT NULL,
    `value`      TEXT NULL,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
