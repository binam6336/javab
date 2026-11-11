-- تحلیل دانشنامه: هشدارهای محتوایی آخرین تحلیل
CREATE TABLE IF NOT EXISTS `kb_issues` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `run_id`          CHAR(16) NOT NULL,
    `knowledge_id`    INT UNSIGNED NOT NULL,
    `type`            VARCHAR(20) NOT NULL,
    `reason`          TEXT NOT NULL,
    `related`         VARCHAR(255) NOT NULL DEFAULT '',
    `excerpt`         TEXT NOT NULL,
    `related_excerpt` TEXT NULL,
    `content_crc`     INT UNSIGNED NOT NULL DEFAULT 0,
    `related_crc`     INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_kb_issues_run` (`run_id`, `knowledge_id`),
    CONSTRAINT `fk_kb_issues_knowledge` FOREIGN KEY (`knowledge_id`) REFERENCES `knowledge` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- استثناها: دانشنامه‌هایی که هشدارشان از لیست فعال کنار گذاشته شده (محتوا تغییر نمی‌کند)
CREATE TABLE IF NOT EXISTS `kb_exceptions` (
    `knowledge_id` INT UNSIGNED NOT NULL,
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`knowledge_id`),
    CONSTRAINT `fk_kb_exceptions_knowledge` FOREIGN KEY (`knowledge_id`) REFERENCES `knowledge` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
