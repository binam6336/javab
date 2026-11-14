-- امتیاز (۱ تا ۵ ستاره) و بازخورد کاربر روی پاسخ‌های دستیار؛ در پاسخ‌های بعدی به سوال‌های مشابه اثر دارد
CREATE TABLE IF NOT EXISTS `answer_feedback` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `message_id`      BIGINT UNSIGNED NOT NULL,
    `conversation_id` CHAR(32) NOT NULL,
    `question`        TEXT NOT NULL,
    `question_norm`   TEXT NOT NULL,
    `answer`          TEXT NOT NULL,
    `rating`          TINYINT UNSIGNED NULL,
    `comment`         TEXT NULL,
    `is_active`       TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_feedback_message` (`message_id`),
    KEY `idx_feedback_active` (`is_active`, `rating`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- اصلاح پاسخ: پاسخ درست برای یک سوال؛ از این به بعد بر دانشنامه مقدم است و پاسخ اشتباه تکرار نمی‌شود
CREATE TABLE IF NOT EXISTS `answer_corrections` (
    `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `message_id`       BIGINT UNSIGNED NULL,
    `conversation_id`  CHAR(32) NULL,
    `question`         TEXT NOT NULL,
    `question_norm`    TEXT NOT NULL,
    `original_answer`  TEXT NOT NULL,
    `corrected_answer` TEXT NOT NULL,
    `is_active`        TINYINT(1) NOT NULL DEFAULT 1,
    `hits`             INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_correction_message` (`message_id`),
    KEY `idx_correction_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
