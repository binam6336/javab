<?php /** @var string $message @var int $status */ ?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>خطا</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/vazirmatn@33.0.3/Vazirmatn-font-face.css">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body>
<div class="error-page">
    <div class="error-card">
        <div class="error-code"><?= e(fa_num($status)) ?></div>
        <p><?= e($message) ?></p>
        <a class="btn btn-primary" href="<?= e(url('/')) ?>">بازگشت به صفحه‌ی اصلی</a>
    </div>
</div>
</body>
</html>
