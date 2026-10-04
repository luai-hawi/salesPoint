/*
 * SalesPoint UI helpers: toasts, confirm dialogs and a CSRF-aware JSON fetch.
 * Exposed as window.SP. Texts come from window.SP_I18N (injected by the layout).
 *
 *   SP.toast('Saved', 'success');
 *   SP.confirm({ title, message, confirmText, danger: true }).then(ok => ...);
 *   SP.fetchJson('/url', { method: 'POST', body: {...} }).then(data => ...);
 *
 * Declarative confirmation: <form data-confirm="Delete this?"> or <button data-confirm="...">.
 */
(function () {
    'use strict';

    var i18n = window.SP_I18N || {};
    var t = function (key, fallback) {
        return i18n[key] || fallback;
    };

    var isRtl = function () {
        return document.documentElement.getAttribute('dir') === 'rtl';
    };

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function el(tag, className, text) {
        var node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined && text !== null) node.textContent = text;
        return node;
    }

    // ── Toasts ──────────────────────────────────────────────────────────
    var toastHost = null;

    function ensureToastHost() {
        if (toastHost && document.body.contains(toastHost)) return toastHost;
        toastHost = el('div', 'fixed inset-x-0 bottom-4 z-[10000] flex flex-col items-center gap-2 px-4 pointer-events-none');
        toastHost.setAttribute('aria-live', 'polite');
        document.body.appendChild(toastHost);
        return toastHost;
    }

    function toast(message, type, duration) {
        if (!message) return;
        var tones = {
            success: 'bg-green-600',
            error: 'bg-red-600',
            warning: 'bg-amber-500',
            info: 'bg-gray-900'
        };
        var node = el('div', 'pointer-events-auto max-w-md rounded-lg px-4 py-2.5 text-sm font-medium text-white shadow-lg transition-all duration-200 opacity-0 translate-y-2 ' + (tones[type] || tones.info), message);
        node.setAttribute('role', 'status');
        ensureToastHost().appendChild(node);
        requestAnimationFrame(function () {
            node.classList.remove('opacity-0', 'translate-y-2');
        });
        setTimeout(function () {
            node.classList.add('opacity-0', 'translate-y-2');
            setTimeout(function () {
                node.remove();
            }, 220);
        }, duration || (type === 'error' ? 6000 : 3500));
    }

    // ── Confirm dialog ──────────────────────────────────────────────────
    function confirmDialog(options) {
        options = options || {};
        return new Promise(function (resolve) {
            var overlay = el('div', 'fixed inset-0 z-[10001] flex items-center justify-center bg-black/50 p-4');
            overlay.setAttribute('role', 'dialog');
            overlay.setAttribute('aria-modal', 'true');
            if (isRtl()) overlay.setAttribute('dir', 'rtl');

            var box = el('div', 'w-full max-w-sm rounded-2xl bg-white p-5 shadow-2xl');
            box.appendChild(el('h3', 'text-base font-semibold text-gray-900', options.title || t('confirm_title', 'Are you sure?')));
            if (options.message) {
                var messageEl = document.createElement('p');
                messageEl.className = 'mt-2 text-sm text-gray-600';
                if (options.html) {
                    messageEl.innerHTML = options.message;
                } else {
                    messageEl.textContent = options.message;
                }
                box.appendChild(messageEl);
            }

            var actions = el('div', 'mt-5 flex justify-end gap-2');
            var cancel = el('button', 'rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50', options.cancelText || t('cancel', 'Cancel'));
            cancel.type = 'button';
            var ok = el('button', 'rounded-lg px-4 py-2 text-sm font-semibold text-white ' + (options.danger === false ? 'bg-indigo-600 hover:bg-indigo-700' : 'bg-red-600 hover:bg-red-700'), options.confirmText || t('confirm', 'Confirm'));
            ok.type = 'button';
            actions.appendChild(cancel);
            actions.appendChild(ok);
            box.appendChild(actions);
            overlay.appendChild(box);
            document.body.appendChild(overlay);

            function close(result) {
                document.removeEventListener('keydown', onKey);
                overlay.remove();
                resolve(result);
            }

            function onKey(event) {
                if (event.key === 'Escape') close(false);
            }

            cancel.addEventListener('click', function () { close(false); });
            ok.addEventListener('click', function () { close(true); });
            overlay.addEventListener('click', function (event) {
                if (event.target === overlay) close(false);
            });
            document.addEventListener('keydown', onKey);
            ok.focus();
        });
    }

    // ── JSON fetch with CSRF ────────────────────────────────────────────
    function isPlainObject(value) {
        return Array.isArray(value) || Object.prototype.toString.call(value) === '[object Object]';
    }

    function fetchJson(url, options) {
        options = options || {};
        var headers = Object.assign({
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken()
        }, options.headers || {});

        var hasContentType = Object.keys(headers).some(function (name) {
            return name.toLowerCase() === 'content-type';
        });

        var body = options.body;
        if (isPlainObject(body)) {
            headers['Content-Type'] = 'application/json';
            body = JSON.stringify(body);
        } else if (typeof body === 'string' && !hasContentType) {
            // Callers often pass an already serialised JSON string; without this header Laravel would not parse it.
            headers['Content-Type'] = 'application/json';
        }

        return fetch(url, Object.assign({}, options, {
            headers: headers,
            body: body,
            credentials: 'same-origin'
        })).then(function (response) {
            return response.text().then(function (text) {
                var data = null;
                try { data = text ? JSON.parse(text) : null; } catch (e) { data = null; }
                if (!response.ok) {
                    var error = new Error((data && (data.message || data.error)) || t('error', 'Something went wrong'));
                    error.status = response.status;
                    error.data = data;
                    throw error;
                }
                return data;
            });
        });
    }

    // ── Declarative confirmation (data-confirm) ─────────────────────────
    var bypass = new WeakSet();

    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!(form instanceof HTMLFormElement) || bypass.has(form)) return;
        var trigger = event.submitter && event.submitter.getAttribute('data-confirm') ? event.submitter : null;
        var message = (trigger && trigger.getAttribute('data-confirm')) || form.getAttribute('data-confirm');
        if (!message) return;
        event.preventDefault();
        var submitter = event.submitter;
        confirmDialog({ message: message, danger: form.getAttribute('data-confirm-tone') !== 'primary' }).then(function (ok) {
            if (!ok) return;
            bypass.add(form);
            if (submitter && form.requestSubmit) {
                form.requestSubmit(submitter);
            } else {
                form.submit();
            }
        });
    }, true);

    document.addEventListener('click', function (event) {
        var button = event.target.closest && event.target.closest('a[data-confirm]');
        if (!button) return;
        event.preventDefault();
        confirmDialog({ message: button.getAttribute('data-confirm') }).then(function (ok) {
            if (ok) window.location.href = button.href;
        });
    }, true);

    window.SP = Object.assign(window.SP || {}, {
        toast: toast,
        confirm: confirmDialog,
        fetchJson: fetchJson,
        csrfToken: csrfToken
    });
})();
