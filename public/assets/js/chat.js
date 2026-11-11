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
        var text = m.content || m.message || '';
        var status = m.status || 'answered';
        var el = document.createElement('div');
        el.className = 'msg msg-bot';
        var body = '';

        if (status === 'answered' || status === 'ok') {
            body += '<div class="msg-text">' + J.esc(text) + '</div>';
            body += '<div class="msg-meta">';
            if (m.confidence != null) {
                body += '<span class="conf" title="درصد اطمینان پاسخ">' +
                    '<span class="conf-bar"><i data-w="' + m.confidence + '"></i></span>' + J.faNum(m.confidence) + '٪ تطابق</span>';
            }
            (m.sources || []).forEach(function (s) {
                body += '<span class="src" title="' + J.esc(s.question) + '">منبع: ' + J.esc(s.question) + '</span>';
            });
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
            (navigator.clipboard ? navigator.clipboard.writeText(text) : Promise.reject()).then(function () {
                copy.title = 'کپی شد';
            }).catch(function () {});
        });
        var retry = el.querySelector('[data-retry]');
        if (retry) retry.addEventListener('click', function () {
            if (busy) return;
            el.remove();
            send(retryText, true);
        });
        list.appendChild(el);
        scrollDown(true);
        return el;
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
