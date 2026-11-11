/* دانشنامه: شمارنده‌ی کاراکتر و ارسال با Ctrl+Enter */
(function () {
    'use strict';
    var J = window.Javab;

    document.querySelectorAll('textarea[data-count]').forEach(function (ta) {
        var max = parseInt(ta.getAttribute('maxlength') || '0', 10);
        var counter = document.createElement('span');
        counter.className = 'char-count';
        ta.insertAdjacentElement('afterend', counter);
        var update = function () {
            counter.textContent = J.faNum(ta.value.length) + ' / ' + J.faNum(max);
        };
        ta.addEventListener('input', update);
        ta.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && (e.ctrlKey || e.metaKey) && ta.form) {
                e.preventDefault();
                ta.form.requestSubmit ? ta.form.requestSubmit() : ta.form.submit();
            }
        });
        update();
    });

    var first = document.querySelector('.kb-form textarea[name="answer"]');
    var q = document.querySelector('.kb-form textarea[name="question"]');
    if (q && first && q.value && !first.value) first.focus();
})();
