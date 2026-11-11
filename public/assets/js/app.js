/* جواب — اسکریپت مشترک همه‌ی صفحات */
(function () {
    'use strict';

    var meta = function (name) {
        var el = document.querySelector('meta[name="' + name + '"]');
        return el ? el.getAttribute('content') : '';
    };
    var BASE = meta('base-url') || '/';
    var STORE_KEY = 'javab.conversations';

    function url(path) {
        return BASE.replace(/\/$/, '') + '/' + String(path || '').replace(/^\//, '');
    }

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function faNum(v) {
        return String(v).replace(/\d/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'[d]; });
    }

    function http(method, path, body) {
        var opts = {
            method: method,
            headers: { 'Accept': 'application/json', 'X-CSRF-Token': meta('csrf-token') },
            credentials: 'same-origin'
        };
        if (body !== undefined) {
            opts.headers['Content-Type'] = 'application/json';
            opts.body = JSON.stringify(body);
        }
        return fetch(url(path), opts).then(function (res) {
            return res.json().catch(function () { return { ok: false, status: 'error', message: 'پاسخ نامعتبر از سرور' }; })
                .then(function (data) { data._http = res.status; return data; });
        });
    }

    // ---------------- ذخیره‌ی گفتگوها در مرورگر ----------------
    var store = {
        all: function () {
            try { return JSON.parse(localStorage.getItem(STORE_KEY) || '[]') || []; } catch (e) { return []; }
        },
        save: function (list) {
            try { localStorage.setItem(STORE_KEY, JSON.stringify(list.slice(0, 50))); } catch (e) { /* ignore */ }
        },
        upsert: function (id, title) {
            var list = store.all().filter(function (c) { return c.id !== id; });
            var old = store.all().filter(function (c) { return c.id === id; })[0];
            list.unshift({ id: id, title: (old && old.title) || title, ts: Date.now() });
            store.save(list);
        },
        remove: function (id) {
            store.save(store.all().filter(function (c) { return c.id !== id; }));
        }
    };

    function newId() {
        var a = new Uint8Array(16);
        (window.crypto || window.msCrypto).getRandomValues(a);
        return Array.prototype.map.call(a, function (b) { return ('0' + b.toString(16)).slice(-2); }).join('');
    }

    function renderConversations(activeId) {
        var section = document.querySelector('[data-conv-section]');
        var ul = document.querySelector('[data-conv-list]');
        if (!section || !ul) return;
        var list = store.all();
        section.hidden = list.length === 0;
        ul.innerHTML = list.map(function (c) {
            return '<li class="conv-item' + (c.id === activeId ? ' is-active' : '') + '">' +
                '<a href="' + esc(url('/') + '?c=' + c.id) + '" data-conv="' + esc(c.id) + '" title="' + esc(c.title) + '">' + esc(c.title) + '</a>' +
                '<button type="button" class="conv-del" data-conv-del="' + esc(c.id) + '" aria-label="حذف از فهرست" title="حذف از فهرست">' +
                '<svg class="ic ic-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>' +
                '</button></li>';
        }).join('');
    }

    // ---------------- سایدبار موبایل ----------------
    document.addEventListener('click', function (e) {
        if (e.target.closest('[data-sidebar-open]')) {
            document.body.classList.add('sb-open');
        } else if (e.target.closest('[data-sidebar-close]')) {
            document.body.classList.remove('sb-open');
        }
        var del = e.target.closest('[data-conv-del]');
        if (del) {
            e.preventDefault();
            var id = del.getAttribute('data-conv-del');
            store.remove(id);
            document.dispatchEvent(new CustomEvent('javab:conv-removed', { detail: { id: id } }));
            renderConversations(window.Javab.activeConversation);
        }
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') document.body.classList.remove('sb-open');
    });

    // ---------------- تأیید قبل از ارسال فرم ----------------
    document.addEventListener('submit', function (e) {
        var msg = e.target.getAttribute('data-confirm');
        if (msg && !window.confirm(msg)) e.preventDefault();
    });

    window.Javab = {
        url: url,
        esc: esc,
        faNum: faNum,
        http: http,
        store: store,
        newId: newId,
        renderConversations: renderConversations,
        activeConversation: null
    };

    renderConversations(null);
})();
