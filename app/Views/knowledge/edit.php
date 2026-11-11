<?php
/** @var array $row @var array $old */
use App\Core\View;
?>
<div class="page page-narrow">
    <div class="page-head">
        <div>
            <h1>ویرایش پرسش و پاسخ <span class="muted">#<?= e(fa_num($row['id'])) ?></span></h1>
        </div>
    </div>
    <section class="card">
        <?= View::render('knowledge/_form', [
            'old'    => $old,
            'action' => '/knowledge/' . $row['id'] . '/update',
            'submit' => 'ذخیره‌ی تغییرات',
            'cancel' => '/knowledge',
        ], null) ?>
    </section>
</div>
