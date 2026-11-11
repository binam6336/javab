<?php /** @var array $rows */ ?>
<div class="page">
    <div class="page-head">
        <div>
            <h1>سوال‌های بی‌پاسخ</h1>
            <p class="muted">سوال‌هایی که دستیار برایشان پاسخی با دقت حداقل <?= e(fa_num((int) config('kb.min_confidence'))) ?>٪ نداشت. با پاسخ دادن به آن‌ها دانشنامه را کامل کنید.</p>
        </div>
    </div>

    <section class="card">
        <?php if (!$rows): ?>
            <div class="empty">
                <?= icon('check', 'ic ic-xl') ?>
                <p>سوال بی‌پاسخی وجود ندارد.</p>
            </div>
        <?php else: ?>
            <ul class="kb-list">
                <?php foreach ($rows as $r): ?>
                    <li class="kb-item">
                        <div class="kb-q"><?= e($r['question']) ?></div>
                        <div class="kb-meta">
                            <span class="pill"><?= e(fa_num($r['asks'])) ?> بار پرسیده شده</span>
                            <?php if ($r['best_confidence'] !== null && (int) $r['best_confidence'] > 0): ?>
                                <span class="pill pill-warn">بیشترین تطابق <?= e(fa_num($r['best_confidence'])) ?>٪</span>
                            <?php endif; ?>
                            <span class="muted"><?= icon('clock', 'ic ic-xs') ?> <?= e(fa_num(date('Y/m/d H:i', strtotime((string) $r['last_asked_at'])))) ?></span>
                            <span class="kb-actions">
                                <a class="btn btn-primary sm" href="<?= e(url('/knowledge') . '?from=' . (int) $r['id']) ?>"><?= icon('plus', 'ic ic-sm') ?><span>پاسخ بده</span></a>
                                <form method="post" action="<?= e(url('/unanswered/' . $r['id'] . '/dismiss')) ?>" data-confirm="این سوال از فهرست حذف شود؟">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-ghost sm" type="submit">نادیده بگیر</button>
                                </form>
                            </span>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>
