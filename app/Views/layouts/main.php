<?php
/** @var string $content @var string $title */
use App\Core\Csrf;
use App\Core\Session;
use App\Models\KbIssue;
use App\Models\Knowledge;
use App\Models\Unanswered;

$page = $page ?? '';
$appName = (string) config('name');
$flashes = Session::pullFlash();
try {
    $openUnanswered = Unanswered::openCount();
    $kbTotal = Knowledge::count();
    $kbIssues = KbIssue::activeCount();
} catch (\Throwable $e) {
    $kbIssues = 0;
    $openUnanswered = 0;
    $kbTotal = 0;
}
?><!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="<?= e(Csrf::token()) ?>">
    <meta name="base-url" content="<?= e(url('/')) ?>">
    <meta name="theme-color" content="#332d75">
    <title><?= e($title ?? '') ?> · <?= e($appName) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/vazirmatn@33.0.3/Vazirmatn-font-face.css">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
    <link rel="icon" href="<?= e(asset('img/logo.svg')) ?>" type="image/svg+xml">
</head>
<body class="page-<?= e($page) ?>">
<div class="app">
    <aside class="sidebar" id="sidebar" aria-label="منوی اصلی">
        <div class="sb-head">
            <a class="brand" href="<?= e(url('/')) ?>">
                <span class="brand-mark"><?= icon('spark') ?></span>
                <span class="brand-name"><?= e($appName) ?></span>
            </a>
            <button class="icon-btn sb-close" type="button" data-sidebar-close aria-label="بستن منو"><?= icon('close') ?></button>
        </div>

        <a class="btn-new" href="<?= e(url('/')) ?>" data-new-chat>
            <?= icon('plus') ?><span>گفتگوی جدید</span>
        </a>

        <nav class="sb-nav">
            <a class="sb-link <?= $page === 'chat' ? 'is-active' : '' ?>" href="<?= e(url('/')) ?>"><?= icon('chat') ?><span>گفتگو با دستیار</span></a>
            <a class="sb-link <?= $page === 'knowledge' ? 'is-active' : '' ?>" href="<?= e(url('/knowledge')) ?>"><?= icon('book') ?><span>دانشنامه</span><em class="sb-count"><?= e(fa_num($kbTotal)) ?></em></a>
            <a class="sb-link <?= $page === 'analysis' ? 'is-active' : '' ?>" href="<?= e(url('/analysis')) ?>"><?= icon('chart') ?><span>تحلیل دانشنامه‌ها</span><?php if ($kbIssues > 0): ?><em class="sb-badge" title="دانشنامه‌های دارای هشدار"><?= e(fa_num($kbIssues)) ?></em><?php endif; ?></a>
            <a class="sb-link <?= $page === 'feedback' ? 'is-active' : '' ?>" href="<?= e(url('/feedback')) ?>"><?= icon('star') ?><span>بازخورد و اصلاح‌ها</span></a>
            <a class="sb-link <?= $page === 'unanswered' ? 'is-active' : '' ?>" href="<?= e(url('/unanswered')) ?>"><?= icon('help') ?><span>سوال‌های بی‌پاسخ</span><?php if ($openUnanswered > 0): ?><em class="sb-badge"><?= e(fa_num($openUnanswered)) ?></em><?php endif; ?></a>
        </nav>

        <div class="sb-section" data-conv-section hidden>
            <div class="sb-title">گفتگوهای اخیر</div>
            <ul class="conv-list" data-conv-list></ul>
        </div>

        <div class="sb-foot">
            <div class="sb-foot-card">
                <?= icon('book', 'ic ic-sm') ?>
                <span>هرچه دانشنامه کامل‌تر باشد، پاسخ‌ها دقیق‌ترند.</span>
            </div>
        </div>
    </aside>
    <div class="sb-backdrop" data-sidebar-close></div>

    <main class="main">
        <header class="topbar">
            <button class="icon-btn" type="button" data-sidebar-open aria-label="باز کردن منو"><?= icon('menu') ?></button>
            <span class="topbar-title"><?= e($title ?? $appName) ?></span>
            <a class="icon-btn" href="<?= e(url('/')) ?>" data-new-chat aria-label="گفتگوی جدید"><?= icon('plus') ?></a>
        </header>

        <?php if ($flashes): ?>
            <div class="flashes">
                <?php foreach ($flashes as $f): ?>
                    <div class="flash flash-<?= e($f['type']) ?>" role="status"><?= e($f['message']) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?= $content ?>
    </main>
</div>
<script src="<?= e(asset('js/app.js')) ?>"></script>
<?php foreach (($scripts ?? []) as $s): ?>
<script src="<?= e(asset('js/' . $s . '.js')) ?>"></script>
<?php endforeach; ?>
</body>
</html>
