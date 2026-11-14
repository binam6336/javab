/* تحلیل دانشنامه‌ها: طول نمودار و اجرای مرحله‌به‌مرحله‌ی تحلیل */
(function () {
    'use strict';
    var J = window.Javab;

    var btn = document.querySelector('[data-analysis-run]');
    var status = document.querySelector('[data-analysis-status]');
    if (!btn || !status) return;

    function show(text, isError) {
        status.textContent = text;
        status.classList.toggle('an-error', !!isError);
    }
    function fail(message) {
        show(message || 'تحلیل انجام نشد.', true);
        btn.disabled = false;
    }
    function step() {
        J.http('POST', '/api/analysis/step', {}).then(function (res) {
            if (!res.ok) return fail(res.message);
            if (res.done) {
                show('تحلیل کامل شد؛ در حال بارگذاری نتایج…');
                location.reload();
                return;
            }
            if (res.wait) {
                show('به سقف درخواست هوش مصنوعی رسیدیم؛ ادامه‌ی تحلیل تا ' + J.faNum(res.wait) + ' ثانیه‌ی دیگر…');
                setTimeout(step, res.wait * 1000);
                return;
            }
            show('در حال تحلیل… بخش ' + J.faNum(res.step + 1) + ' از ' + J.faNum(res.total));
            step();
        }).catch(function () { fail('ارتباط با سرور قطع شد.'); });
    }

    btn.addEventListener('click', function () {
        btn.disabled = true;
        show('در حال آماده‌سازی…');
        J.http('POST', '/api/analysis/start', {}).then(function (res) {
            if (!res.ok) return fail(res.message);
            show('در حال تحلیل… بخش ۱ از ' + J.faNum(res.total));
            step();
        }).catch(function () { fail('ارتباط با سرور قطع شد.'); });
    });
})();
