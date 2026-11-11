<?php
/** @var array $list @var string $search @var array $old @var int $rulesActive */
use App\Core\View;
use App\Models\Rule;
?>
<div class="page">
    <div class="page-head page-head-row">
        <div>
            <h1>دانشنامه</h1>
            <p class="muted">هر پرسش و پاسخی که اینجا ثبت می‌کنید، منبع دانش دستیار است.</p>
        </div>
        <button type="button" class="btn btn-accent" data-rules-open aria-haspopup="dialog">
            <?= icon('rules', 'ic ic-sm') ?><span>قوانین ثابت</span>
            <span class="pill pill-dark" data-rules-count<?= $rulesActive ? '' : ' hidden' ?>><?= e(fa_num($rulesActive)) ?></span>
        </button>
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

<dialog class="modal" id="rules-modal" aria-labelledby="rules-title">
    <div class="modal-box">
        <div class="modal-head">
            <div>
                <h2 class="modal-title" id="rules-title"><?= icon('rules', 'ic') ?> قوانین ثابت</h2>
                <p class="muted modal-sub">قانون‌هایی که روی <b>همه‌ی پاسخ‌ها</b> اعمال می‌شوند؛ مثل لحن، قالب یا جمله‌ی پایانی. هر ردیف یک قانون است.</p>
            </div>
            <button type="button" class="icon-btn" data-rules-close aria-label="بستن" title="بستن"><?= icon('close', 'ic') ?></button>
        </div>

        <form class="rule-add" data-rules-form>
            <textarea name="body" rows="2" maxlength="<?= Rule::BODY_MAX ?>" required placeholder="مثلاً: پاسخ همیشه باید با لحنی محترمانه اما صمیمی باشد."></textarea>
            <button class="btn btn-primary" type="submit"><?= icon('plus', 'ic ic-sm') ?><span>افزودن</span></button>
        </form>
        <p class="rule-msg" data-rules-msg role="status" hidden></p>

        <ul class="rule-list" data-rules-list aria-live="polite"></ul>
        <div class="empty rule-empty" data-rules-empty hidden>
            <?= icon('rules', 'ic ic-xl') ?>
            <p>هنوز قانونی ثبت نشده است.</p>
        </div>
        <p class="field-hint rule-foot">قانون‌های غیرفعال ذخیره می‌مانند ولی روی پاسخ‌ها اثری ندارند. وقتی قانون فعالی وجود دارد، همه‌ی پاسخ‌ها (حتی تطابق‌های کامل) از هوش مصنوعی عبور می‌کنند تا قوانین اعمال شوند.</p>
    </div>
</dialog>
