/* صفحه‌ی گفتگو (شبیه ChatGPT) */
(function () {
    'use strict';
    var J = window.Javab;

    var root = document.querySelector('[data-chat]');
    if (!root) return;

    var scroller = root.querySelector('[data-chat-scroll]');
    var welcome = root.querySelector('[data-welcome]');
    var list = root.querySelector('[data-messages]');
    var form = root.querySelector('[data-composer]');
    var input = root.querySelector('[data-input]');
    var sendBtn = root.querySelector('[data-send]');

    var ICON_BOT = '<svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l1.8 5.2L19 10l-5.2 1.8L12 17l-1.8-5.2L5 10l5.2-1.8z"/></svg>';
    var ICON_COPY = '<svg class="ic ic-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="8" y="8" width="12" height="12" rx="2"/><path d="M16 8V5a1 1 0 0 0-1-1H5a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h3"/></svg>';

    var STAR = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3.5l2.6 5.3 5.9.9-4.3 4.1 1 5.8-5.2-2.7-5.2 2.7 1-5.8-4.3-4.1 5.9-.9z"/></svg>';
    var ICON_EDIT = '<svg class="ic ic-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20h4L19 9l-4-4L4 16z"/></svg>';
    var ICON_NOTE = '<svg class="ic ic-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 5h16v11H8l-4 4z"/><path d="M8 9h8M8 12h5"/></svg>';

    var conversation = null;
    var busy = false;
    var cooldownTimer = null;

    // ---------------- گفتگو ----------------
    function startNew() {
        conversation = J.newId();
        J.activeConversation = conversation;
        list.innerHTML = '';
        welcome.hidden = false;
        history.replaceState(null, '', J.url('/'));
        J.renderConversations(null);
        input.focus();
    }

    function open(id) {
        conversation = id;
        J.activeConversation = id;
        list.innerHTML = '';
        welcome.hidden = true;
        J.renderConversations(id);
        var loading = addTyping();
        J.http('GET', '/api/chat/' + id).then(function (res) {
            loading.remove();
            var msgs = (res && res.messages) || [];
            if (!msgs.length) { welcome.hidden = false; return; }
            msgs.forEach(function (m) {
                if (m.role === 'user') addUser(m.content);
                else addBot(m);
            });
            scrollDown(false);
        }).catch(function () {
            loading.remove();
            addBot({ status: 'error', message: 'بارگذاری گفتگو ممکن نشد.' });
        });
    }

    // ---------------- رندر پیام‌ها ----------------
    function addUser(text) {
        welcome.hidden = true;
        var el = document.createElement('div');
        el.className = 'msg msg-user';
        el.innerHTML = '<div class="msg-body">' + J.esc(text) + '</div>';
        list.appendChild(el);
        scrollDown(true);
        return el;
    }

    function addTyping() {
        var el = document.createElement('div');
        el.className = 'msg msg-bot';
        el.innerHTML = '<div class="msg-avatar">' + ICON_BOT + '</div><div class="msg-body"><span class="typing"><i></i><i></i><i></i></span></div>';
        list.appendChild(el);
        scrollDown(true);
        return el;
    }

    function addBot(m, retryText) {
        welcome.hidden = true;
        var original = m.content || m.message || '';
        var text = m.corrected != null ? m.corrected : original;
        var status = m.status || 'answered';
        if (m.corrected != null) status = 'answered';
        var el = document.createElement('div');
        el.className = 'msg msg-bot';
        var body = '';

        if (status === 'answered' || status === 'ok') {
            body += '<div class="msg-text" data-text>' + J.esc(text) + '</div>';
            body += '<div class="msg-meta">';
            if (m.corrected != null) {
                body += '<span class="fixed-badge" data-fixed title="این پاسخ در گفتگو ویرایش شده است">' + ICON_EDIT + 'پاسخ اصلاح‌شده</span>';
            } else if (m.confidence != null) {
                body += '<span class="conf" title="درصد اطمینان پاسخ">' +
                    '<span class="conf-bar"><i data-w="' + m.confidence + '"></i></span>' + J.faNum(m.confidence) + '٪ تطابق</span>';
            }
            if (m.corrected == null) {
                (m.sources || []).forEach(function (s) {
                    var label = s.type === 'correction' ? 'از اصلاح‌ها: ' : 'منبع: ';
                    body += '<span class="src' + (s.type === 'correction' ? ' src-fix' : '') + '" title="' + J.esc(s.question) + '">' + label + J.esc(s.question) + '</span>';
                });
            }
            body += '<button type="button" class="msg-tool" data-copy aria-label="کپی پاسخ" title="کپی">' + ICON_COPY + '</button>';
            body += '</div>';
        } else {
            body += '<div class="msg-note ' + J.esc(status) + '">' + J.esc(text);
            if (retryText) {
                body += '<div><button type="button" class="btn btn-ghost sm retry-btn" data-retry>دوباره امتحان کن</button></div>';
            }
            body += '</div>';
        }

        el.innerHTML = '<div class="msg-avatar">' + ICON_BOT + '</div><div class="msg-body">' + body + '</div>';
        // عرض نوار اطمینان (بدون style درون‌خطی در HTML)
        var bar = el.querySelector('.conf-bar i');
        if (bar) bar.style.width = Math.max(0, Math.min(100, +bar.getAttribute('data-w'))) + '%';
        var copy = el.querySelector('[data-copy]');
        if (copy) copy.addEventListener('click', function () {
            var t = el.querySelector('[data-text]');
            (navigator.clipboard ? navigator.clipboard.writeText(t ? t.textContent : text) : Promise.reject()).then(function () {
                copy.title = 'کپی شد';
            }).catch(function () {});
        });
        var retry = el.querySelector('[data-retry]');
        if (retry) retry.addEventListener('click', function () {
            if (busy) return;
            el.remove();
            send(retryText, true);
        });
        if (m.message_id) attachFeedback(el, m, original, text);
        list.appendChild(el);
        scrollDown(true);
        return el;
    }

    // ---------------- امتیاز، بازخورد و ویرایش پاسخ ----------------
    function attachFeedback(el, m, original, current) {
        var id = m.message_id;
        var rating = m.rating || 0;
        var comment = m.comment || '';
        var bodyEl = el.querySelector('.msg-body');

        var bar = document.createElement('div');
        bar.className = 'msg-fb';
        var stars = '';
        for (var i = 1; i <= 5; i++) {
            stars += '<button type="button" class="fb-star" data-star="' + i + '" aria-label="' + J.faNum(i) + ' ستاره" title="' + J.faNum(i) + ' ستاره">' + STAR + '</button>';
        }
        bar.innerHTML = '<span class="fb-stars" role="group" aria-label="امتیاز به این پاسخ">' + stars + '</span>' +
            '<button type="button" class="msg-tool" data-fb-comment title="نوشتن بازخورد" aria-label="نوشتن بازخورد">' + ICON_NOTE + '</button>' +
            '<button type="button" class="msg-tool" data-fb-edit title="ویرایش پاسخ" aria-label="ویرایش پاسخ">' + ICON_EDIT + '</button>' +
            '<span class="fb-msg" data-fb-msg role="status"></span>';
        bodyEl.appendChild(bar);

        var note = document.createElement('div');
        note.className = 'fb-note';
        bodyEl.appendChild(note);

        function paintStars(n) {
            bar.querySelectorAll('[data-star]').forEach(function (b) {
                var on = +b.getAttribute('data-star') <= n;
                b.classList.toggle('on', on);
                b.setAttribute('aria-pressed', +b.getAttribute('data-star') === rating ? 'true' : 'false');
            });
        }
        function say(text, err) {
            var s = bar.querySelector('[data-fb-msg]');
            s.textContent = text || '';
            s.classList.toggle('is-err', !!err);
            clearTimeout(say.t);
            if (text && !err) say.t = setTimeout(function () { s.textContent = ''; }, 3500);
        }
        function showComment() {
            note.innerHTML = comment ? '<span class="fb-note-label">بازخورد شما:</span> ' + J.esc(comment) : '';
        }
        paintStars(rating);
        showComment();

        bar.addEventListener('mouseover', function (e) {
            var b = e.target.closest('[data-star]');
            if (b) paintStars(+b.getAttribute('data-star'));
        });
        bar.addEventListener('mouseleave', function () { paintStars(rating); });

        function save(payload, done) {
            J.http('POST', '/api/chat/message/' + id + '/rate', payload).then(function (res) {
                if (!res.ok) { say(res.message, true); paintStars(rating); return; }
                rating = res.rating || 0;
                comment = res.comment || '';
                paintStars(rating);
                showComment();
                say('ثبت شد');
                if (done) done();
            }).catch(function () { say('ارتباط برقرار نشد', true); paintStars(rating); });
        }

        function openComment() {
            if (note.querySelector('form')) return;
            note.innerHTML = '<form class="fb-form"><textarea rows="2" maxlength="1000" placeholder="چه چیزی در این پاسخ درست نبود یا چطور بهتر می‌شد؟">' + J.esc(comment) + '</textarea>' +
                '<div class="fb-form-actions"><button class="btn btn-primary sm" type="submit">ثبت بازخورد</button>' +
                '<button class="btn btn-ghost sm" type="button" data-cancel>انصراف</button></div></form>';
            var f = note.querySelector('form');
            var ta = f.querySelector('textarea');
            ta.focus();
            f.addEventListener('submit', function (e) {
                e.preventDefault();
                save({ comment: ta.value.trim() });
            });
            f.querySelector('[data-cancel]').addEventListener('click', showComment);
            ta.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') showComment();
                if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) { e.preventDefault(); f.requestSubmit(); }
            });
        }

        bar.addEventListener('click', function (e) {
            var b = e.target.closest('[data-star]');
            if (b) {
                var n = +b.getAttribute('data-star');
                save({ rating: n }, function () { if (n <= 2 && !comment) openComment(); });
                return;
            }
            if (e.target.closest('[data-fb-comment]')) openComment();
            if (e.target.closest('[data-fb-edit]')) openEdit();
        });

        // ویرایش پاسخ (اصلاح)
        function openEdit() {
            if (el.querySelector('.fix-form')) return;
            var form = document.createElement('form');
            form.className = 'fix-form';
            var isAnswered = !!el.querySelector('[data-text]');
            form.innerHTML = '<label class="fix-label">پاسخ درست را بنویسید؛ از این به بعد برای این سوال و سوال‌های مشابه همین پاسخ ملاک است.</label>' +
                '<textarea rows="4" maxlength="5000" required>' + J.esc(isAnswered ? current : '') + '</textarea>' +
                '<div class="fb-form-actions"><button class="btn btn-primary sm" type="submit">ذخیره‌ی اصلاح</button>' +
                '<button class="btn btn-ghost sm" type="button" data-cancel>انصراف</button></div>';
            bar.parentNode.insertBefore(form, bar);
            var ta = form.querySelector('textarea');
            ta.focus();
            ta.setSelectionRange(ta.value.length, ta.value.length);
            form.querySelector('[data-cancel]').addEventListener('click', function () { form.remove(); });
            ta.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') form.remove();
                if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) { e.preventDefault(); form.requestSubmit(); }
            });
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                var answer = ta.value.trim();
                if (!answer) return;
                var btn = form.querySelector('[type=submit]');
                btn.disabled = true;
                J.http('POST', '/api/chat/message/' + id + '/correct', { answer: answer }).then(function (res) {
                    btn.disabled = false;
                    if (!res.ok) { say(res.message, true); return; }
                    current = res.corrected;
                    form.remove();
                    showCorrected(current);
                    say('پاسخ اصلاح شد');
                }).catch(function () { btn.disabled = false; say('ارتباط برقرار نشد', true); });
            });
        }

        function showCorrected(text) {
            var t = el.querySelector('[data-text]');
            if (!t) {
                // پیام «پاسخی پیدا نشد» → تبدیل به پاسخ
                var n = el.querySelector('.msg-note');
                t = document.createElement('div');
                t.className = 'msg-text';
                t.setAttribute('data-text', '');
                if (n) n.replaceWith(t); else bodyEl.insertBefore(t, bodyEl.firstChild);
            }
            t.textContent = text;
            var meta = el.querySelector('.msg-meta');
            if (!meta) {
                meta = document.createElement('div');
                meta.className = 'msg-meta';
                t.insertAdjacentElement('afterend', meta);
            }
            el.querySelectorAll('.src, .conf').forEach(function (x) { x.remove(); });
            if (!el.querySelector('[data-fixed]')) {
                var badge = document.createElement('span');
                badge.className = 'fixed-badge';
                badge.setAttribute('data-fixed', '');
                badge.title = 'این پاسخ در گفتگو ویرایش شده است';
                badge.innerHTML = ICON_EDIT + 'پاسخ اصلاح‌شده';
                meta.insertBefore(badge, meta.firstChild);
            }
        }
    }

    function scrollDown(smooth) {
        scroller.scrollTo({ top: scroller.scrollHeight, behavior: smooth ? 'smooth' : 'auto' });
    }

    // ---------------- ارسال ----------------
    function send(text, isRetry) {
        text = (text || '').trim();
        if (!text || busy) return;
        busy = true;
        updateButton();

        if (!isRetry) addUser(text);
        J.store.upsert(conversation, text.slice(0, 60));
        J.renderConversations(conversation);
        if (location.search.indexOf(conversation) === -1) {
            history.replaceState(null, '', J.url('/') + '?c=' + conversation);
        }

        var typing = addTyping();
        J.http('POST', '/api/chat/ask', { conversation: conversation, question: text })
            .then(function (res) {
                typing.remove();
                var retryable = res.status === 'rate_limited' || res.status === 'error';
                addBot(res, retryable ? text : null);
                if (res.status === 'rate_limited' && res.retry_after) cooldown(res.retry_after);
            })
            .catch(function () {
                typing.remove();
                addBot({ status: 'error', message: 'ارتباط با سرور برقرار نشد.' }, text);
            })
            .then(function () {
                busy = false;
                updateButton();
                input.focus();
            });
    }

    // شمارش معکوس وقتی سقف درخواست پر شده است
    function cooldown(seconds) {
        clearInterval(cooldownTimer);
        var left = seconds;
        var placeholder = input.getAttribute('placeholder');
        input.dataset.ph = input.dataset.ph || placeholder;
        root.dataset.cooldown = '1';
        updateButton();
        cooldownTimer = setInterval(function () {
            left--;
            input.setAttribute('placeholder', 'لطفاً ' + J.faNum(Math.max(left, 0)) + ' ثانیه صبر کنید…');
            if (left <= 0) {
                clearInterval(cooldownTimer);
                delete root.dataset.cooldown;
                input.setAttribute('placeholder', input.dataset.ph);
                updateButton();
            }
        }, 1000);
    }

    function updateButton() {
        sendBtn.disabled = busy || !!root.dataset.cooldown || input.value.trim() === '';
    }

    function autoGrow() {
        input.style.height = 'auto';
        input.style.height = Math.min(input.scrollHeight, 200) + 'px';
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (sendBtn.disabled) return;
        var text = input.value;
        input.value = '';
        autoGrow();
        updateButton();
        send(text);
    });

    input.addEventListener('input', function () { autoGrow(); updateButton(); });
    input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) {
            e.preventDefault();
            form.requestSubmit ? form.requestSubmit() : form.dispatchEvent(new Event('submit', { cancelable: true }));
        }
    });

    root.addEventListener('click', function (e) {
        var s = e.target.closest('[data-suggest]');
        if (s && !busy && !root.dataset.cooldown) send(s.getAttribute('data-suggest'));
    });

    // «گفتگوی جدید» و گفتگوهای قبلی بدون بارگذاری مجدد صفحه
    document.addEventListener('click', function (e) {
        var n = e.target.closest('[data-new-chat]');
        if (n) {
            e.preventDefault();
            document.body.classList.remove('sb-open');
            startNew();
            return;
        }
        var c = e.target.closest('[data-conv]');
        if (c) {
            e.preventDefault();
            document.body.classList.remove('sb-open');
            var id = c.getAttribute('data-conv');
            history.replaceState(null, '', J.url('/') + '?c=' + id);
            open(id);
        }
    });

    document.addEventListener('javab:conv-removed', function (e) {
        if (e.detail.id === conversation) startNew();
    });

    // ---------------- شروع ----------------
    var fromUrl = new URLSearchParams(location.search).get('c');
    if (fromUrl && /^[a-f0-9]{32}$/.test(fromUrl)) open(fromUrl);
    else startNew();
})();
