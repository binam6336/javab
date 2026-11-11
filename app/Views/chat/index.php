<?php /** @var string[] $samples @var int $kbCount */ ?>
<section class="chat" data-chat>
    <div class="chat-scroll" data-chat-scroll>
        <div class="chat-welcome" data-welcome>
            <div class="welcome-mark"><?= icon('spark', 'ic ic-xl') ?></div>
            <h1>سلام! چطور می‌توانم کمکتان کنم؟</h1>
            <p class="muted">
                <?php if ($kbCount > 0): ?>
                    پاسخ‌ها فقط بر اساس <strong><?= e(fa_num($kbCount)) ?></strong> پرسش و پاسخ دانشنامه داده می‌شود
                    و اگر دقت پاسخ کمتر از <?= e(fa_num((int) config('kb.min_confidence'))) ?>٪ باشد، پاسخی ارسال نمی‌شود.
                <?php else: ?>
                    دانشنامه هنوز خالی است. ابتدا از بخش <a href="<?= e(url('/knowledge')) ?>">دانشنامه</a> چند پرسش و پاسخ اضافه کنید.
                <?php endif; ?>
            </p>
            <?php if ($samples): ?>
                <div class="suggest">
                    <?php foreach ($samples as $s): ?>
                        <button type="button" class="suggest-item" data-suggest="<?= e($s) ?>">
                            <?= icon('chat', 'ic ic-sm') ?><span><?= e(short_text($s, 70)) ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="messages" data-messages aria-live="polite"></div>
    </div>

    <div class="composer-wrap">
        <form class="composer" data-composer autocomplete="off">
            <textarea name="question" rows="1" maxlength="1000" placeholder="سوال خود را بنویسید…" data-input aria-label="سوال" required></textarea>
            <button type="submit" class="send-btn" data-send aria-label="ارسال" disabled><?= icon('send') ?></button>
        </form>
        <p class="composer-hint">Enter برای ارسال · Shift+Enter برای خط جدید</p>
    </div>
</section>
