<?php
/**
 * @var string $tab @var string $filter @var array $summary
 * @var array|null $ratings @var array|null $corrections @var array $counts
 */
use App\Models\Correction;

$stars = static function (?int $n): string {
    $out = '<span class="stars" role="img" aria-label="' . ($n ? e(fa_num($n)) . ' از ۵ ستاره' : 'بدون امتیاز') . '">';
    for ($i = 1; $i <= 5; $i++) {
        $out .= '<svg class="star' . ($n !== null && $i <= $n ? ' on' : '') . '" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3.5l2.6 5.3 5.9.9-4.3 4.1 1 5.8-5.2-2.7-5.2 2.7 1-5.8-4.3-4.1 5.9-.9z"/></svg>';
    }
    return $out . '</span>';
};
$when = static function (string $dt): string {
    return fa_num(date('Y/m/d H:i', (int) strtotime($dt)));
};
$hidden = static function (string $filter, int $page): string {
    return csrf_field()
        . '<input type="hidden" name="filter" value="' . e($filter) . '">'
        . '<input type="hidden" name="page" value="' . $page . '">';
};
$list = $tab === 'ratings' ? $ratings : $corrections;
?>
<div class="page">
    <div class="page-head">
        <h1>بازخورد و اصلاح‌ها</h1>
        <p class="muted">امتیازها، بازخوردها و پاسخ‌های اصلاح‌شده در گفتگو. موارد فعال در پاسخ‌های بعدی به سوال‌های مشابه اثر دارند.</p>
    </div>

    <section class="card">
        <nav class="tabs" aria-label="بخش">
            <a class="tab <?= $tab === 'ratings' ? 'is-active' : '' ?>" href="<?= e(url('/feedback')) ?>">امتیازها و بازخوردها</a>
            <a class="tab <?= $tab === 'corrections' ? 'is-active' : '' ?>" href="<?= e(url('/feedback?tab=corrections')) ?>">اصلاح‌ها <span class="pill"><?= e(fa_num($counts['corrections'])) ?></span></a>
        </nav>

        <?php if ($tab === 'ratings'): ?>
            <div class="fb-summary">
                <div class="fb-avg">
                    <b><?= e(fa_num($summary['avg'])) ?></b>
                    <?= $stars((int) round($summary['avg'])) ?>
                    <span class="muted"><?= e(fa_num($summary['count'])) ?> امتیاز</span>
                </div>
                <ul class="fb-dist">
                    <?php foreach ($summary['dist'] as $star => $c): $pct = $summary['count'] ? (int) round($c / $summary['count'] * 100) : 0; ?>
                        <li><span><?= e(fa_num($star)) ?> ستاره</span><span class="us-track"><span class="us-bar" data-w="<?= $pct ?>"></span></span><b><?= e(fa_num($c)) ?></b></li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <nav class="chips" aria-label="فیلتر">
                <?php foreach (['all' => 'همه', 'low' => 'کم‌امتیاز (۱ و ۲)', 'high' => 'پرامتیاز (۴ و ۵)', 'comment' => 'دارای بازخورد متنی'] as $k => $label): ?>
                    <a class="chip <?= $filter === $k ? 'is-active' : '' ?>" href="<?= e(url('/feedback') . ($k === 'all' ? '' : '?filter=' . $k)) ?>"><?= e($label) ?></a>
                <?php endforeach; ?>
            </nav>
        <?php else: ?>
            <p class="field-hint fb-hint">وقتی پاسخی را در گفتگو ویرایش می‌کنید، اینجا ثبت می‌شود. برای همان سوال (و سوال‌های هم‌معنی) پاسخ اصلاح‌شده ملاک قرار می‌گیرد و پاسخ اشتباه قبلی تکرار نمی‌شود.</p>
        <?php endif; ?>

        <?php if (!$list['rows']): ?>
            <div class="empty">
                <?= icon($tab === 'ratings' ? 'star' : 'edit', 'ic ic-xl') ?>
                <p><?= $tab === 'ratings' ? 'هنوز امتیاز یا بازخوردی ثبت نشده است. در گفتگو زیر هر پاسخ می‌توانید امتیاز بدهید.' : 'هنوز پاسخی اصلاح نشده است. در گفتگو با دکمه‌ی ویرایش زیر هر پاسخ، آن را اصلاح کنید.' ?></p>
            </div>
        <?php elseif ($tab === 'ratings'): ?>
            <ul class="fb-list">
                <?php foreach ($ratings['rows'] as $r): $active = (int) $r['is_active'] === 1; ?>
                    <li class="fb-item<?= $active ? '' : ' is-off' ?>">
                        <div class="fb-head">
                            <?= $stars($r['rating'] !== null ? (int) $r['rating'] : null) ?>
                            <span class="muted fb-date"><?= e($when((string) $r['updated_at'])) ?></span>
                            <?php if (!$active): ?><span class="pill">بدون اثر</span><?php endif; ?>
                        </div>
                        <div class="fb-q"><?= e($r['question']) ?></div>
                        <div class="fb-a"><?= nl2br(e(short_text((string) $r['answer'], 300))) ?></div>
                        <?php if (trim((string) $r['comment']) !== ''): ?>
                            <blockquote class="is-quote fb-comment"><span class="is-label">بازخورد</span><?= e($r['comment']) ?></blockquote>
                        <?php endif; ?>
                        <div class="fb-actions">
                            <a class="btn btn-ghost sm" href="<?= e(url('/') . '?c=' . $r['conversation_id']) ?>">مشاهده‌ی گفتگو</a>
                            <form method="post" action="<?= e(url('/feedback/' . $r['id'] . '/toggle')) ?>">
                                <?= $hidden($filter, $ratings['page']) ?>
                                <input type="hidden" name="active" value="<?= $active ? '0' : '1' ?>">
                                <button class="btn btn-ghost sm" type="submit"><?= $active ? 'غیرفعال کردن اثر' : 'فعال کردن اثر' ?></button>
                            </form>
                            <form method="post" action="<?= e(url('/feedback/' . $r['id'] . '/delete')) ?>" data-confirm="این بازخورد حذف شود؟">
                                <?= $hidden($filter, $ratings['page']) ?>
                                <button class="icon-btn sm danger" type="submit" aria-label="حذف" title="حذف"><?= icon('trash', 'ic ic-sm') ?></button>
                            </form>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <ul class="fb-list">
                <?php foreach ($corrections['rows'] as $r): $active = (int) $r['is_active'] === 1; ?>
                    <li class="fb-item<?= $active ? '' : ' is-off' ?>">
                        <div class="fb-head">
                            <span class="fb-q"><?= e($r['question']) ?></span>
                            <?php if (!$active): ?><span class="pill">غیرفعال</span><?php endif; ?>
                            <?php if ((int) $r['hits'] > 0): ?><span class="pill pill-acc"><?= e(fa_num($r['hits'])) ?> بار استفاده</span><?php endif; ?>
                        </div>
                        <div class="cr-grid">
                            <div class="cr-old"><span class="is-label">پاسخ قبلی (اشتباه)</span><?= nl2br(e(short_text((string) $r['original_answer'], 400))) ?></div>
                            <div class="cr-new"><span class="is-label">پاسخ اصلاح‌شده</span><?= nl2br(e($r['corrected_answer'])) ?></div>
                        </div>
                        <details class="cr-edit">
                            <summary class="btn btn-ghost sm"><?= icon('edit', 'ic ic-sm') ?> ویرایش</summary>
                            <form method="post" action="<?= e(url('/corrections/' . $r['id'] . '/update')) ?>">
                                <?= $hidden('all', $corrections['page']) ?>
                                <label class="field">
                                    <span class="field-label">سوال</span>
                                    <textarea name="question" rows="2" maxlength="1000" required><?= e($r['question']) ?></textarea>
                                </label>
                                <label class="field">
                                    <span class="field-label">پاسخ اصلاح‌شده</span>
                                    <textarea name="answer" rows="4" maxlength="<?= Correction::ANSWER_MAX ?>" required><?= e($r['corrected_answer']) ?></textarea>
                                </label>
                                <button class="btn btn-primary sm" type="submit">ذخیره</button>
                            </form>
                        </details>
                        <div class="fb-actions">
                            <span class="muted fb-date"><?= e($when((string) $r['updated_at'])) ?></span>
                            <?php if ($r['conversation_id']): ?>
                                <a class="btn btn-ghost sm" href="<?= e(url('/') . '?c=' . $r['conversation_id']) ?>">مشاهده‌ی گفتگو</a>
                            <?php endif; ?>
                            <form method="post" action="<?= e(url('/corrections/' . $r['id'] . '/toggle')) ?>">
                                <?= $hidden('all', $corrections['page']) ?>
                                <input type="hidden" name="active" value="<?= $active ? '0' : '1' ?>">
                                <button class="btn btn-ghost sm" type="submit"><?= $active ? 'غیرفعال کردن' : 'فعال کردن' ?></button>
                            </form>
                            <form method="post" action="<?= e(url('/corrections/' . $r['id'] . '/delete')) ?>" data-confirm="این اصلاح حذف شود؟ پاسخ‌های بعدی دیگر از آن پیروی نمی‌کنند.">
                                <?= $hidden('all', $corrections['page']) ?>
                                <button class="icon-btn sm danger" type="submit" aria-label="حذف" title="حذف"><?= icon('trash', 'ic ic-sm') ?></button>
                            </form>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if ($list['pages'] > 1): ?>
            <nav class="pager" aria-label="صفحه‌بندی">
                <?php for ($p = 1; $p <= $list['pages']; $p++):
                    $qs = array_filter(['tab' => $tab === 'corrections' ? 'corrections' : null, 'filter' => $filter !== 'all' ? $filter : null, 'page' => $p]); ?>
                    <a class="<?= $p === $list['page'] ? 'is-active' : '' ?>" href="<?= e(url('/feedback') . '?' . http_build_query($qs)) ?>"><?= e(fa_num($p)) ?></a>
                <?php endfor; ?>
            </nav>
        <?php endif; ?>
    </section>
</div>
