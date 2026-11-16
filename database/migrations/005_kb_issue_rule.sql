-- تحلیل دانشنامه: قانون ثابتی که یک دانشنامه با آن مغایرت دارد (برای نوع rule_violation)
ALTER TABLE `kb_issues` ADD COLUMN `rule_id` INT UNSIGNED NULL AFTER `related_crc`;
