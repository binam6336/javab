<?php
/** @var array $old @var string $action @var string $submit */
use App\Models\Knowledge;
?>
<form class="kb-form" method="post" action="<?= e(url($action)) ?>">
    <?= csrf_field() ?>
    <?php if (!empty($old['from'])): ?>
        <input type="hidden" name="from" value="<?= e($old['from']) ?>">
    <?php endif; ?>
    <label class="field">
        <span class="field-label">سوال</span>
        <textarea name="question" rows="2" maxlength="<?= Knowledge::QUESTION_MAX ?>" required placeholder="مثلاً: ساعت کاری پشتیبانی چیست؟" data-count><?= e($old['question'] ?? '') ?></textarea>
        <span class="field-hint">سوال را همان‌طور بنویسید که کاربران معمولاً می‌پرسند.</span>
    </label>
    <label class="field">
        <span class="field-label">پاسخ</span>
        <textarea name="answer" rows="5" maxlength="<?= Knowledge::ANSWER_MAX ?>" required placeholder="پاسخ کامل و دقیق…" data-count><?= e($old['answer'] ?? '') ?></textarea>
    </label>
    <div class="form-actions">
        <button class="btn btn-primary" type="submit"><?= icon('check', 'ic ic-sm') ?><span><?= e($submit) ?></span></button>
        <?php if (!empty($cancel)): ?>
            <a class="btn btn-ghost" href="<?= e(url($cancel)) ?>">انصراف</a>
        <?php endif; ?>
    </div>
</form>
