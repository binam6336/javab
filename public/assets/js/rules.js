/* قوانین ثابت: پاپ‌آپ مدیریت در صفحه‌ی دانشنامه */
(function () {
    'use strict';
    var J = window.Javab;
    var modal = document.getElementById('rules-modal');
    var openBtn = document.querySelector('[data-rules-open]');
    if (!modal || !openBtn) return;

    var list = modal.querySelector('[data-rules-list]');
    var empty = modal.querySelector('[data-rules-empty]');
    var form = modal.querySelector('[data-rules-form]');
    var msg = modal.querySelector('[data-rules-msg]');
    var counter = document.querySelector('[data-rules-count]');
    var rules = [];
    var loaded = false;

    var ICON = {
        edit: '<path d="M4 20h4L19 9l-4-4L4 16z"/>',
        trash: '<path d="M5 7h14M10 7V4h4v3M7 7l1 13h8l1-13"/>'
    };
    function svg(name) {
        return '<svg class="ic ic-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + ICON[name] + '</svg>';
    }

    function say(text, ok) {
        msg.textContent = text || '';
        msg.hidden = !text;
        msg.classList.toggle('is-ok', !!ok);
        clearTimeout(say.t);
        if (text && ok) say.t = setTimeout(function () { msg.hidden = true; }, 2500);
    }

    function updateCount() {
        var n = rules.filter(function (r) { return r.active; }).length;
        if (counter) {
            counter.textContent = J.faNum(n);
            counter.hidden = n === 0;
        }
    }

    function render() {
        empty.hidden = rules.length > 0;
        list.innerHTML = rules.map(function (r) {
            return '<li class="rule-item' + (r.active ? '' : ' is-off') + '" data-id="' + r.id + '">' +
                '<div class="rule-body">' + J.esc(r.body) + '</div>' +
                '<div class="rule-tools">' +
                '<label class="switch" title="' + (r.active ? 'فعال' : 'غیرفعال') + '"><input type="checkbox" data-act="toggle"' + (r.active ? ' checked' : '') + ' aria-label="فعال بودن قانون"><span></span></label>' +
                '<button type="button" class="icon-btn sm" data-act="edit" aria-label="ویرایش" title="ویرایش">' + svg('edit') + '</button>' +
                '<button type="button" class="icon-btn sm danger" data-act="delete" aria-label="حذف" title="حذف">' + svg('trash') + '</button>' +
                '</div></li>';
        }).join('');
        updateCount();
    }

    function load() {
        list.innerHTML = '<li class="muted">در حال بارگذاری…</li>';
        J.http('GET', '/api/rules').then(function (res) {
            if (!res.ok) { say(res.message || 'خطا در دریافت قوانین'); return; }
            rules = res.rules || [];
            loaded = true;
            render();
        }).catch(function () { say('ارتباط با سرور برقرار نشد.'); });
    }

    function find(id) {
        for (var i = 0; i < rules.length; i++) if (String(rules[i].id) === String(id)) return i;
        return -1;
    }

    function replace(rule) {
        var i = find(rule.id);
        if (i >= 0) rules[i] = rule;
        render();
    }

    // باز و بسته کردن
    openBtn.addEventListener('click', function () {
        say('');
        if (typeof modal.showModal === 'function') modal.showModal(); else modal.setAttribute('open', '');
        if (!loaded) load();
        setTimeout(function () { form.body.focus(); }, 30);
    });
    modal.addEventListener('click', function (e) {
        if (e.target === modal || e.target.closest('[data-rules-close]')) modal.close();
    });

    // افزودن
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var body = form.body.value.trim();
        if (body.length < 3) { say('متن قانون را بنویسید (حداقل ۳ کاراکتر).'); return; }
        var btn = form.querySelector('button[type="submit"]');
        btn.disabled = true;
        J.http('POST', '/api/rules', { body: body }).then(function (res) {
            if (!res.ok) { say(res.message); return; }
            rules.push(res.rule);
            form.body.value = '';
            render();
            say(res.message, true);
        }).catch(function () { say('ارتباط با سرور برقرار نشد.'); })
            .then(function () { btn.disabled = false; form.body.focus(); });
    });
    form.body.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) { e.preventDefault(); form.requestSubmit ? form.requestSubmit() : form.dispatchEvent(new Event('submit')); }
    });

    // فعال/غیرفعال
    list.addEventListener('change', function (e) {
        var input = e.target.closest('[data-act="toggle"]');
        if (!input) return;
        var id = input.closest('.rule-item').getAttribute('data-id');
        input.disabled = true;
        J.http('POST', '/api/rules/' + id + '/update', { active: input.checked }).then(function (res) {
            if (!res.ok) { say(res.message); input.checked = !input.checked; input.disabled = false; return; }
            replace(res.rule);
            say(res.rule.active ? 'قانون فعال شد.' : 'قانون غیرفعال شد.', true);
        }).catch(function () { say('ارتباط با سرور برقرار نشد.'); input.checked = !input.checked; input.disabled = false; });
    });

    // ویرایش و حذف
    list.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-act]');
        if (!btn) return;
        var li = btn.closest('.rule-item');
        var id = li.getAttribute('data-id');
        var act = btn.getAttribute('data-act');
        var rule = rules[find(id)];

        if (act === 'delete') {
            if (!window.confirm('این قانون حذف شود؟')) return;
            btn.disabled = true;
            J.http('POST', '/api/rules/' + id + '/delete', {}).then(function (res) {
                if (!res.ok) { say(res.message); btn.disabled = false; return; }
                rules.splice(find(id), 1);
                render();
                say(res.message, true);
            }).catch(function () { say('ارتباط با سرور برقرار نشد.'); btn.disabled = false; });
        } else if (act === 'edit') {
            li.innerHTML = '<form class="rule-edit"><textarea name="body" rows="3" maxlength="1000" required>' + J.esc(rule.body) + '</textarea>' +
                '<div class="rule-edit-actions"><button class="btn btn-primary sm" type="submit">ذخیره</button>' +
                '<button class="btn btn-ghost sm" type="button" data-act="cancel">انصراف</button></div></form>';
            var ta = li.querySelector('textarea');
            ta.focus();
            ta.setSelectionRange(ta.value.length, ta.value.length);
            ta.addEventListener('keydown', function (ev) {
                if (ev.key === 'Escape') { ev.preventDefault(); ev.stopPropagation(); render(); }
                if (ev.key === 'Enter' && (ev.ctrlKey || ev.metaKey)) { ev.preventDefault(); li.querySelector('form').requestSubmit(); }
            });
            li.querySelector('form').addEventListener('submit', function (ev) {
                ev.preventDefault();
                var body = ta.value.trim();
                if (body.length < 3) { say('متن قانون را بنویسید (حداقل ۳ کاراکتر).'); return; }
                J.http('POST', '/api/rules/' + id + '/update', { body: body }).then(function (res) {
                    if (!res.ok) { say(res.message); return; }
                    replace(res.rule);
                    say(res.message, true);
                }).catch(function () { say('ارتباط با سرور برقرار نشد.'); });
            });
        } else if (act === 'cancel') {
            render();
        }
    });
})();
