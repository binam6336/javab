-- قوانین ثابت: هر ردیف یک قانون که روی همه‌ی پاسخ‌ها اعمال می‌شود
CREATE TABLE IF NOT EXISTS `rules` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `body`       TEXT NOT NULL,
    `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
    `sort`       INT NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_rules_active` (`is_active`, `sort`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
