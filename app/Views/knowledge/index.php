<?php
/** @var array $list @var string $search @var array $old */
use App\Core\View;
?>
<div class="page">
    <div class="page-head">
        <div>
            <h1>دانشنامه</h1>
            <p class="muted">هر پرسش و پاسخی که اینجا ثبت می‌کنید، منبع دانش دستیار است.</p>
        </div>
    </div>

    <div class="grid-2">
        <section class="card">
            <h2 class="card-title"><?= icon('plus', 'ic ic-sm') ?> افزودن پرسش و پاسخ</h2>
            <?= View::render('knowledge/_form', ['old' => $old, 'action' => '/knowledge', 'submit' => 'ذخیره در دانشنامه'], null) ?>
        </section>

        <section class="card">
            <div class="card-head">
                <h2 class="card-title"><?= icon('book', 'ic ic-sm') ?> موارد ثبت‌شده <span class="pill"><?= e(fa_num($list['total'])) ?></span></h2>
                <form class="search" method="get" action="<?= e(url('/knowledge')) ?>">
                    <?= icon('search', 'ic ic-sm') ?>
                    <input type="search" name="q" value="<?= e($search) ?>" placeholder="جستجو در دانشنامه…" aria-label="جستجو">
                </form>
            </div>

            <?php if (!$list['rows']): ?>
                <div class="empty">
                    <?= icon('book', 'ic ic-xl') ?>
                    <p><?= $search !== '' ? 'موردی با این عبارت پیدا نشد.' : 'هنوز پرسش و پاسخی ثبت نشده است.' ?></p>
                </div>
            <?php else: ?>
                <ul class="kb-list">
                    <?php foreach ($list['rows'] as $row): ?>
                        <li class="kb-item">
                            <div class="kb-q"><?= e($row['question']) ?></div>
                            <div class="kb-a"><?= nl2br(e(short_text((string) $row['answer'], 260))) ?></div>
                            <div class="kb-meta">
                                <span class="muted">#<?= e(fa_num($row['id'])) ?></span>
                                <?php if ((int) $row['hits'] > 0): ?>
                                    <span class="pill pill-acc"><?= e(fa_num($row['hits'])) ?> بار استفاده</span>
                                <?php endif; ?>
                                <span class="kb-actions">
                                    <a class="icon-btn sm" href="<?= e(url('/knowledge/' . $row['id'] . '/edit')) ?>" aria-label="ویرایش" title="ویرایش"><?= icon('edit', 'ic ic-sm') ?></a>
                                    <form method="post" action="<?= e(url('/knowledge/' . $row['id'] . '/delete')) ?>" data-confirm="این پرسش و پاسخ حذف شود؟">
                                        <?= csrf_field() ?>
                                        <button class="icon-btn sm danger" type="submit" aria-label="حذف" title="حذف"><?= icon('trash', 'ic ic-sm') ?></button>
                                    </form>
                                </span>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <?php if ($list['pages'] > 1): ?>
                    <nav class="pager" aria-label="صفحه‌بندی">
                        <?php for ($p = 1; $p <= $list['pages']; $p++): ?>
                            <a class="<?= $p === $list['page'] ? 'is-active' : '' ?>" href="<?= e(url('/knowledge') . '?' . http_build_query(['q' => $search, 'page' => $p])) ?>"><?= e(fa_num($p)) ?></a>
                        <?php endfor; ?>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    </div>
</div>
