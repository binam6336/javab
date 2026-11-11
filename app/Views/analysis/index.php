<?php
/**
 * @var array $usage @var array|null $last @var bool $stale @var string $tab
 * @var array $active @var array $excepted
 */
use App\Models\KbIssue;

$max = $usage['rows'] ? max(1, $usage['rows'][0]['hits']) : 1;
$visible = 10;
$list = $tab === 'exceptions' ? $excepted : $active;

$bar = static function (array $r, int $rank) use ($max): string {
    $pct = max(2, (int) round($r['hits'] / $max * 100));
    $count = fa_num($r['hits']);
    return '<li class="us-row" title="' . e($r['question']) . ' — ' . e($count) . ' سؤال">'
        . '<span class="us-rank">' . e(fa_num($rank)) . '</span>'
        . '<a class="us-q" href="' . e(url('/knowledge/' . $r['id'] . '/edit')) . '">' . e($r['question']) . '</a>'
        . '<span class="us-track"><span class="us-bar" data-w="' . $pct . '"></span></span>'
        . '<span class="us-num"><b>' . e($count) . '</b> سؤال</span>'
        . '</li>';
};
?>
<div class="page">
    <div class="page-head">
        <h1>تحلیل دانشنامه‌ها</h1>
        <p class="muted">میزان استفاده از هر دانشنامه و مواردی که ممکن است باعث پاسخ اشتباه شوند.</p>
    </div>

    <!-- ۱) آمار استفاده -->
    <section class="card an-card">
        <div class="card-head">
            <h2 class="card-title"><?= icon('chart', 'ic ic-sm') ?> آمار استفاده</h2>
            <span class="muted an-sum">
                <?= e(fa_num($usage['total_hits'])) ?> سؤال پاسخ‌داده‌شده،
                <?= e(fa_num($usage['used'])) ?> از <?= e(fa_num($usage['total'])) ?> دانشنامه استفاده شده
            </span>
        </div>
        <?php if (!$usage['rows']): ?>
            <div class="empty">
                <?= icon('chart', 'ic ic-xl') ?>
                <p>هنوز به هیچ سؤالی از روی دانشنامه پاسخ داده نشده است.</p>
            </div>
        <?php else: ?>
            <ol class="us-list" aria-label="دانشنامه‌ها به ترتیب تعداد سؤال">
                <?php foreach (array_slice($usage['rows'], 0, $visible) as $i => $r): ?>
                    <?= $bar($r, $i + 1) ?>
                <?php endforeach; ?>
            </ol>
            <?php if (count($usage['rows']) > $visible): ?>
                <details class="us-more">
                    <summary>نمایش همه (<?= e(fa_num(count($usage['rows']))) ?> مورد)</summary>
                    <ol class="us-list" start="<?= $visible + 1 ?>">
                        <?php foreach (array_slice($usage['rows'], $visible) as $i => $r): ?>
                            <?= $bar($r, $visible + $i + 1) ?>
                        <?php endforeach; ?>
                    </ol>
                </details>
            <?php endif; ?>
            <p class="field-hint an-note">هر بار که دستیار با استفاده از یک دانشنامه به سؤالی پاسخ دهد، یک سؤال برای آن شمرده می‌شود.</p>
        <?php endif; ?>
    </section>

    <!-- ۲ و ۳) هشدارها و استثناها -->
    <section class="card an-card">
        <div class="card-head">
            <h2 class="card-title"><?= icon('alert', 'ic ic-sm') ?> دانشنامه‌های مشکل‌دار</h2>
            <button type="button" class="btn btn-primary sm" data-analysis-run><?= icon('spark', 'ic ic-sm') ?><span><?= $last ? 'تحلیل دوباره' : 'شروع تحلیل' ?></span></button>
        </div>

        <p class="an-status muted" data-analysis-status>
            <?php if ($last): ?>
                آخرین تحلیل: <?= e(fa_num(date('Y/m/d H:i', (int) strtotime((string) $last['finished'])))) ?>، <?= e(fa_num((int) $last['items'])) ?> دانشنامه بررسی شد
                <?php if (!empty($last['failed'])): ?>
                    — <span class="an-warn"><?= e(fa_num((int) $last['failed'])) ?> بخش به‌خاطر خطای هوش مصنوعی بررسی نشد</span>
                <?php endif; ?>
                <?php if ($stale): ?>
                    — <span class="an-warn">دانشنامه بعد از این تحلیل تغییر کرده است</span>
                <?php endif; ?>
            <?php else: ?>
                هنوز تحلیلی انجام نشده است. تحلیل با هوش مصنوعی و بر اساس معنای محتوا انجام می‌شود.
            <?php endif; ?>
        </p>

        <nav class="tabs" aria-label="نوع لیست">
            <a class="tab <?= $tab === 'active' ? 'is-active' : '' ?>" href="<?= e(url('/analysis')) ?>">هشدارهای فعال <span class="pill"><?= e(fa_num(count($active))) ?></span></a>
            <a class="tab <?= $tab === 'exceptions' ? 'is-active' : '' ?>" href="<?= e(url('/analysis?tab=exceptions')) ?>">استثناها <span class="pill"><?= e(fa_num(count($excepted))) ?></span></a>
        </nav>

        <?php if (!$list): ?>
            <div class="empty">
                <?= icon($tab === 'exceptions' ? 'check' : 'alert', 'ic ic-xl') ?>
                <p>
                    <?php if ($tab === 'exceptions'): ?>
                        هیچ دانشنامه‌ای در لیست استثناها نیست.
                    <?php elseif ($last): ?>
                        هشدار فعالی وجود ندارد.
                    <?php else: ?>
                        برای پیدا کردن موارد مشکل‌دار، تحلیل را شروع کنید.
                    <?php endif; ?>
                </p>
            </div>
        <?php else: ?>
            <ul class="is-list">
                <?php foreach ($list as $id => $item): $row = $item['row']; ?>
                    <li class="is-item">
                        <div class="is-head">
                            <a class="is-name" href="<?= e(url('/knowledge/' . $id . '/edit')) ?>"><?= e($row['question']) ?></a>
                            <span class="muted is-id">#<?= e(fa_num($id)) ?></span>
                            <?php if ($tab === 'exceptions'): ?>
                                <form method="post" action="<?= e(url('/analysis/' . $id . '/unexcept')) ?>">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-ghost sm" type="submit">خروج از استثنا</button>
                                </form>
                            <?php else: ?>
                                <form method="post" action="<?= e(url('/analysis/' . $id . '/except')) ?>">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-ghost sm" type="submit" title="هشدار از لیست فعال کنار گذاشته می‌شود؛ محتوای دانشنامه تغییر نمی‌کند">استثنا</button>
                                </form>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($item['edited'])): ?>
                            <p class="is-edited"><?= icon('clock', 'ic ic-sm') ?> این دانشنامه یا مورد مرتبطش بعد از تحلیل ویرایش شده است؛ ممکن است مشکل برطرف شده باشد.</p>
                        <?php endif; ?>
                        <?php if (!$item['issues']): ?>
                            <p class="muted is-none">در آخرین تحلیل هشداری برای این دانشنامه ثبت نشده است.</p>
                        <?php endif; ?>

                        <?php foreach ($item['issues'] as $iss): ?>
                            <div class="is-issue">
                                <span class="is-type t-<?= e($iss['type']) ?>"><?= e(KbIssue::TYPES[$iss['type']] ?? $iss['type']) ?></span>
                                <p class="is-reason"><?= e($iss['reason']) ?></p>
                                <blockquote class="is-quote"><span class="is-label">متن مشکل‌دار</span><?= e($iss['excerpt']) ?></blockquote>
                                <?php if ($iss['related']): ?>
                                    <div class="is-rel">
                                        <span class="is-label"><?= icon('link', 'ic ic-sm') ?> <?= in_array($iss['type'], ['contradiction'], true) ? 'دانشنامه‌ی متناقض' : 'دانشنامه‌ی مرتبط' ?></span>
                                        <?php foreach ($iss['related'] as $r): ?>
                                            <a href="<?= e(url('/knowledge/' . $r . '/edit')) ?>">#<?= e(fa_num($r)) ?> — <?= e(short_text((string) $item['related_rows'][$r]['question'], 120)) ?></a>
                                        <?php endforeach; ?>
                                        <?php if ($iss['related_excerpt'] !== ''): ?>
                                            <blockquote class="is-quote"><?= e($iss['related_excerpt']) ?></blockquote>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>

                        <?php if ($tab === 'exceptions'): ?>
                            <p class="field-hint is-when">مستثنا شده در <?= e(fa_num(date('Y/m/d H:i', (int) strtotime((string) $item['excepted_at'])))) ?></p>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>
