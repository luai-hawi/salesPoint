(function () {
    'use strict';

    var shared = window.SPOfflineShared;
    if (!shared) {
        return;
    }

    var translations = window.SPOfflineTranslations || {};
    var userId = window.spCurrentUserId || null;
    var nativeFetch = window.fetch ? window.fetch.bind(window) : null;
    var state = {
        online: navigator.onLine !== false,
        syncing: false,
        queueAvailable: true,
        lastWarmAt: null,
        warmProgress: null
    };
    var callbacks = [];
    var warmResolvers = {};
    var store;
    var queue;
    var syncPromise = null;
    var readyResolved = false;
    var readyReject;
    var readyResolve;
    var ready = new Promise(function (resolve, reject) {
        readyResolve = resolve;
        readyReject = reject;
    });
    var PROBE_TIMEOUT_MS = 1500;

    function t(key, fallback, replacements) {
        var value = translations[key];
        if (!value) {
            value = fallback || key;
        }

        Object.keys(replacements || {}).forEach(function (name) {
            value = value.replace(':' + name, String(replacements[name]));
        });

        return value;
    }

    function emitChange() {
        callbacks.forEach(function (callback) {
            try {
                callback();
            } catch (error) {
                console.warn('[SPOffline] callback failed', error);
            }
        });
    }

    function onChange(callback) {
        callbacks.push(callback);
        return function () {
            callbacks = callbacks.filter(function (item) {
                return item !== callback;
            });
        };
    }

    function setOfflineBanner(offline) {
        var banner = document.getElementById('sp-offline-banner');
        if (!banner) {
            return;
        }

        banner.style.display = offline ? 'flex' : 'none';
    }

    function setCsrfToken(token) {
        var meta = document.querySelector('meta[name="csrf-token"]');
        if (meta && token) {
            meta.setAttribute('content', token);
        }
    }

    function getCsrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    async function probeConnectivity(force) {
        if (!nativeFetch) {
            return false;
        }

        if (!force && state.online === false && navigator.onLine === false) {
            return false;
        }

        var controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
        var timer = controller ? setTimeout(function () {
            controller.abort();
        }, PROBE_TIMEOUT_MS) : null;

        try {
            var response = await nativeFetch('/up?_sp_probe=' + Date.now(), {
                method: 'GET',
                credentials: 'same-origin',
                cache: 'no-store',
                signal: controller ? controller.signal : undefined,
                headers: {
                    'Accept': 'text/plain'
                }
            });

            state.online = !!response && response.ok;
        } catch (error) {
            state.online = false;
        } finally {
            if (timer) {
                clearTimeout(timer);
            }
        }

        setOfflineBanner(!state.online);
        emitChange();

        return state.online;
    }

    function generateId() {
        if (window.crypto && typeof window.crypto.randomUUID === 'function') {
            return window.crypto.randomUUID();
        }

        return 'offline-' + Date.now() + '-' + Math.random().toString(36).slice(2, 10);
    }

    function maybeWarnLargePayload(size) {
        if (size > 25 * 1024 * 1024 && window.SP && typeof window.SP.toast === 'function') {
            window.SP.toast(t('large_file_warning', 'Large files may take longer to sync.'), 'warning');
        }
    }

    function buildItemUrl(url) {
        return new URL(url, window.location.origin);
    }

    function isSameOrigin(url) {
        try {
            return buildItemUrl(url).origin === window.location.origin;
        } catch (error) {
            return false;
        }
    }

    function shouldIgnoreForm(form, submitter) {
        return form.hasAttribute('data-no-offline') ||
            form.hasAttribute('data-offline-ignore') ||
            !!form.closest('[data-offline-ignore]') ||
            !!(submitter && (submitter.hasAttribute('data-no-offline') || submitter.hasAttribute('data-offline-ignore')));
    }

    function formMethods(form, submitter) {
        var transportMethod = String((submitter && (submitter.getAttribute('formmethod') || submitter.formMethod)) || form.getAttribute('method') || 'GET').toUpperCase();
        var data;

        try {
            data = submitter ? new FormData(form, submitter) : new FormData(form);
        } catch (error) {
            data = new FormData(form);
            if (submitter && submitter.name) {
                data.append(submitter.name, submitter.value || '');
            }
        }

        var override = data.get('_method');
        var effectiveMethod = override ? String(override).toUpperCase() : transportMethod;
        var action = (submitter && (submitter.getAttribute('formaction') || submitter.formAction)) || form.getAttribute('action') || window.location.href;

        return {
            action: action,
            transportMethod: transportMethod,
            effectiveMethod: effectiveMethod,
            formData: data
        };
    }

    function encodeFormData(formData) {
        var entries = [];
        formData.forEach(function (value, key) {
            if (typeof Blob !== 'undefined' && value instanceof Blob) {
                entries.push([key, {
                    __blob: true,
                    blob: value,
                    name: value.name || 'upload',
                    type: value.type || 'application/octet-stream',
                    lastModified: value.lastModified || Date.now(),
                    size: value.size || 0
                }]);
                return;
            }

            entries.push([key, String(value)]);
        });

        return entries;
    }

    function decodeFormData(entries) {
        var formData = new FormData();
        (entries || []).forEach(function (entry) {
            var key = entry[0];
            var value = entry[1];
            if (value && value.__blob) {
                formData.append(key, value.blob, value.name || 'upload');
                return;
            }
            formData.append(key, value);
        });
        return formData;
    }

    async function serializeBody(body, headers) {
        if (body === undefined || body === null) {
            return { bodyType: 'empty', bodyData: null };
        }

        if (body instanceof FormData) {
            var encoded = encodeFormData(body);
            return {
                bodyType: 'form-data',
                bodyData: encoded,
                size: shared.estimatePayloadSize(encoded)
            };
        }

        if (typeof URLSearchParams !== 'undefined' && body instanceof URLSearchParams) {
            return {
                bodyType: 'url-search-params',
                bodyData: body.toString(),
                size: body.toString().length
            };
        }

        if (typeof Blob !== 'undefined' && body instanceof Blob) {
            return {
                bodyType: 'blob',
                bodyData: {
                    __blob: true,
                    blob: body,
                    name: body.name || 'blob',
                    type: body.type || headers['Content-Type'] || 'application/octet-stream',
                    size: body.size || 0
                },
                size: body.size || 0
            };
        }

        if (typeof body === 'string') {
            return {
                bodyType: 'text',
                bodyData: body,
                size: body.length
            };
        }

        if (typeof body === 'object') {
            var jsonText = JSON.stringify(body);
            return {
                bodyType: 'text',
                bodyData: jsonText,
                size: jsonText.length
            };
        }

        return {
            bodyType: 'text',
            bodyData: String(body),
            size: String(body).length
        };
    }

    function restoreBody(item) {
        if (item.bodyType === 'form-data') {
            return decodeFormData(item.bodyData);
        }

        if (item.bodyType === 'url-search-params') {
            return item.bodyData ? new URLSearchParams(item.bodyData) : null;
        }

        if (item.bodyType === 'blob' && item.bodyData && item.bodyData.__blob) {
            return item.bodyData.blob;
        }

        if (item.bodyType === 'empty') {
            return null;
        }

        return item.bodyData;
    }

    function syntheticQueuedResponse(id) {
        return new Response(JSON.stringify({
            queued: true,
            offline: true,
            id: id
        }), {
            status: 202,
            headers: {
                'Content-Type': 'application/json',
                'X-SalesPoint-Offline': 'queued'
            }
        });
    }

    function deriveLabelForForm(form) {
        var titleNode = form.closest('[data-offline-label]') ||
            document.querySelector('[data-offline-label]') ||
            document.querySelector('h1, h2, legend');
        var label = form.getAttribute('data-offline-label') ||
            (titleNode ? titleNode.getAttribute('data-offline-label') || titleNode.textContent : '') ||
            document.title;

        return shared.deriveOfflineLabel({
            url: form.getAttribute('action') || window.location.href,
            titleText: label
        });
    }

    async function enqueueItem(payload) {
        await ready;
        var item = await queue.enqueue(payload);
        maybeWarnLargePayload(item.size || 0);
        emitChange();
        return item;
    }

    async function queueFormSubmission(form, submitter) {
        if (!userId) {
            return null;
        }

        var methods = formMethods(form, submitter);
        var actionUrl = buildItemUrl(methods.action);

        if (!shared.shouldQueueMutation(actionUrl.pathname)) {
            return null;
        }

        var encoded = encodeFormData(methods.formData);
        var size = shared.estimatePayloadSize(encoded);
        var item = await enqueueItem({
            id: generateId(),
            idempotencyKey: generateId(),
            source: 'form',
            userId: userId,
            label: deriveLabelForForm(form),
            pageUrl: window.location.href,
            targetUrl: actionUrl.href,
            path: actionUrl.pathname,
            transportMethod: methods.transportMethod,
            effectiveMethod: methods.effectiveMethod,
            headers: {
                'Accept': form.getAttribute('data-offline-accept') || 'text/html,application/xhtml+xml'
            },
            bodyType: 'form-data',
            bodyData: encoded,
            size: size
        });

        if (window.SP && typeof window.SP.toast === 'function') {
            window.SP.toast(state.queueAvailable
                ? t('saved_offline', 'Saved offline — it will sync automatically.')
                : t('memory_fallback', 'Offline queue storage is limited in this browser session.'), state.queueAvailable ? 'success' : 'warning');
        }

        var redirectTo = form.getAttribute('data-offline-redirect');
        if (redirectTo) {
            window.location.href = redirectTo;
        }

        return item;
    }

    async function queueNetworkRequest(details) {
        if (!userId || !shared.shouldQueueMutation(details.path)) {
            return null;
        }

        var serialized = await serializeBody(details.body, details.headers || {});
        var item = await enqueueItem({
            id: generateId(),
            idempotencyKey: details.idempotencyKey || generateId(),
            source: details.source || 'fetch',
            userId: userId,
            label: shared.deriveOfflineLabel({
                url: details.url,
                titleText: document.title
            }),
            pageUrl: window.location.href,
            targetUrl: details.url,
            path: details.path,
            transportMethod: details.transportMethod,
            effectiveMethod: details.effectiveMethod,
            headers: details.headers || {},
            bodyType: serialized.bodyType,
            bodyData: serialized.bodyData,
            size: serialized.size || 0
        });

        if (window.SP && typeof window.SP.toast === 'function') {
            window.SP.toast(state.queueAvailable
                ? t('saved_offline', 'Saved offline — it will sync automatically.')
                : t('memory_fallback', 'Offline queue storage is limited in this browser session.'), state.queueAvailable ? 'success' : 'warning');
        }

        return item;
    }

    function requestDetailsFromFetch(input, init) {
        var request = input instanceof Request ? input : null;
        var url = request ? request.url : input;
        var targetUrl = buildItemUrl(url);
        var headers = {};
        var sourceBody = init && Object.prototype.hasOwnProperty.call(init, 'body') ? init.body : null;
        var transportMethod = String((init && init.method) || (request && request.method) || 'GET').toUpperCase();

        if (request && request.headers) {
            request.headers.forEach(function (value, key) {
                headers[key] = value;
            });
        }
        if (init && init.headers) {
            new Headers(init.headers).forEach(function (value, key) {
                headers[key] = value;
            });
        }

        if (request && sourceBody === null) {
            sourceBody = request.clone().bodyUsed ? null : request.clone();
        }

        return {
            url: targetUrl.href,
            path: targetUrl.pathname,
            transportMethod: transportMethod,
            effectiveMethod: transportMethod,
            headers: headers,
            body: sourceBody
        };
    }

    function ensureFetchIdempotency(input, init, details) {
        var request = input instanceof Request ? input : null;
        var nextInit = Object.assign({}, init || {});
        var headers = new Headers((nextInit && nextInit.headers) || (request && request.headers) || {});
        var key = headers.get('X-Idempotency-Key') || headers.get('x-idempotency-key') || generateId();

        headers.set('X-Idempotency-Key', key);
        nextInit.headers = headers;
        details.idempotencyKey = key;

        if (request) {
            return {
                input: new Request(request, nextInit),
                init: undefined,
                details: details
            };
        }

        return {
            input: input,
            init: nextInit,
            details: details
        };
    }

    async function resolveBodyForFetch(details) {
        if (details.body instanceof Request) {
            var clone = details.body.clone();
            var contentType = details.headers['content-type'] || details.headers['Content-Type'] || '';
            if (contentType.indexOf('application/json') !== -1 || contentType.indexOf('text/') !== -1 || contentType === '') {
                details.body = await clone.text();
                return;
            }

            try {
                details.body = await clone.formData();
            } catch (error) {
                details.body = null;
            }
        }
    }

    function shouldHandleFetchMutation(details) {
        return userId &&
            details.transportMethod !== 'GET' &&
            details.transportMethod !== 'HEAD' &&
            isSameOrigin(details.url) &&
            shared.shouldQueueMutation(details.path);
    }

    function installFetchInterceptor() {
        if (!nativeFetch) {
            return;
        }

        window.fetch = async function (input, init) {
            var details = requestDetailsFromFetch(input, init || {});

            if (!shouldHandleFetchMutation(details)) {
                return nativeFetch(input, init);
            }

            var prepared = ensureFetchIdempotency(input, init, details);
            input = prepared.input;
            init = prepared.init;
            details = prepared.details;

            await resolveBodyForFetch(details);

            try {
                return await nativeFetch(input, init);
            } catch (error) {
                var online = await probeConnectivity(true);
                if (online) {
                    throw error;
                }

                var item = await queueNetworkRequest(Object.assign({ source: 'fetch' }, details));
                if (!item) {
                    throw error;
                }

                return syntheticQueuedResponse(item.id);
            }
        };
    }

    function installAxiosInterceptor() {
        if (!window.axios || typeof window.axios.interceptors !== 'object') {
            return;
        }

        window.axios.interceptors.request.use(function (config) {
            var method = String(config.method || 'GET').toUpperCase();
            var targetUrl = buildItemUrl(config.url || window.location.href);
            if (!userId || method === 'GET' || method === 'HEAD' || !shared.shouldQueueMutation(targetUrl.pathname) || targetUrl.origin !== window.location.origin) {
                return config;
            }

            config.headers = Object.assign({}, config.headers || {});
            if (!config.headers['X-Idempotency-Key'] && !config.headers['x-idempotency-key']) {
                config.headers['X-Idempotency-Key'] = generateId();
            }

            return config;
        });

        window.axios.interceptors.response.use(null, async function (error) {
            var config = error && error.config ? error.config : null;
            if (!config) {
                return Promise.reject(error);
            }

            var method = String(config.method || 'GET').toUpperCase();
            var targetUrl = buildItemUrl(config.url || window.location.href);
            if (!userId || method === 'GET' || method === 'HEAD' || !shared.shouldQueueMutation(targetUrl.pathname) || targetUrl.origin !== window.location.origin) {
                return Promise.reject(error);
            }

            var online = await probeConnectivity(true);
            if (online) {
                return Promise.reject(error);
            }

            var headers = Object.assign({}, config.headers || {});
            var item = await queueNetworkRequest({
                source: 'axios',
                url: targetUrl.href,
                path: targetUrl.pathname,
                transportMethod: method,
                effectiveMethod: method,
                headers: headers,
                body: config.data || null,
                idempotencyKey: headers['X-Idempotency-Key'] || headers['x-idempotency-key'] || generateId()
            });

            if (!item) {
                return Promise.reject(error);
            }

            return Promise.resolve({
                status: 202,
                statusText: 'Accepted',
                headers: {
                    'X-SalesPoint-Offline': 'queued'
                },
                config: config,
                data: {
                    queued: true,
                    offline: true,
                    id: item.id
                }
            });
        });
    }

    function updateItemToken(formData, token) {
        if (!(formData instanceof FormData)) {
            return formData;
        }

        if (formData.has('_token')) {
            formData.set('_token', token);
        } else {
            formData.append('_token', token);
        }

        return formData;
    }

    async function fetchFreshToken() {
        var response = await nativeFetch('/offline/csrf', {
            method: 'GET',
            credentials: 'same-origin',
            cache: 'no-store',
            redirect: 'manual',
            headers: {
                'Accept': 'application/json'
            }
        });

        var location = response.headers.get('Location') || '';
        if (response.status === 0 || response.status === 401 || response.status === 403 || response.status === 301 || response.status === 302 || location.indexOf('/login') !== -1) {
            var authError = new Error('auth');
            authError.code = 'auth';
            throw authError;
        }

        if (!response.ok) {
            throw new Error('csrf');
        }

        var data = await response.json();
        setCsrfToken(data.token);
        return data.token;
    }

    async function replayItem(item) {
        var token = await fetchFreshToken();
        var headers = Object.assign({}, item.headers || {});
        headers['X-CSRF-TOKEN'] = token;
        headers['X-Idempotency-Key'] = item.idempotencyKey || item.id;
        headers['X-Requested-With'] = headers['X-Requested-With'] || 'XMLHttpRequest';

        var body = restoreBody(item);
        if (body instanceof FormData) {
            body = updateItemToken(body, token);
            delete headers['Content-Type'];
            delete headers['content-type'];
        }

        var response = await nativeFetch(item.targetUrl, {
            method: item.transportMethod,
            credentials: 'same-origin',
            redirect: 'follow',
            headers: headers,
            body: body
        });

        return response;
    }

    async function consumeResponseText(response) {
        try {
            return await response.clone().text();
        } catch (error) {
            return '';
        }
    }

    async function markRetry(item, attempts) {
        var backoff = shared.computeBackoffMs(attempts);
        await queue.update(item.id, {
            status: 'pending',
            attempts: attempts,
            nextAttemptAt: Date.now() + backoff,
            errorMessage: ''
        });
    }

    async function markFailed(item, message) {
        await queue.update(item.id, {
            status: 'failed',
            attempts: Number(item.attempts || 0) + 1,
            nextAttemptAt: Date.now(),
            errorMessage: message || t('failed_generic', 'This item needs attention.')
        });
    }

    async function markSyncing(item) {
        await queue.update(item.id, {
            status: 'syncing',
            errorMessage: ''
        });
    }

    async function syncAll() {
        if (!userId) {
            return [];
        }

        await ready;

        if (syncPromise) {
            return syncPromise;
        }

        syncPromise = (async function () {
            state.syncing = true;
            emitChange();

            var results = [];
            var items = await queue.list(userId);
            var now = Date.now();

            for (var i = 0; i < items.length; i += 1) {
                var item = items[i];
                if (item.userId !== userId) {
                    continue;
                }
                if (item.nextAttemptAt && Number(item.nextAttemptAt) > now) {
                    continue;
                }

                await markSyncing(item);
                emitChange();

                try {
                    var response = await replayItem(item);
                    var bodyText = await consumeResponseText(response);
                    var classification = shared.classifyReplayResult({
                        status: response.status,
                        location: response.headers.get('Location') || '',
                        finalUrl: response.url || '',
                        bodyText: bodyText,
                        contentType: response.headers.get('Content-Type') || '',
                        method: item.effectiveMethod
                    });

                    if (classification.state === 'refresh-csrf') {
                        var retryToken = await fetchFreshToken();
                        setCsrfToken(retryToken);
                        response = await replayItem(item);
                        bodyText = await consumeResponseText(response);
                        classification = shared.classifyReplayResult({
                            status: response.status,
                            location: response.headers.get('Location') || '',
                            finalUrl: response.url || '',
                            bodyText: bodyText,
                            contentType: response.headers.get('Content-Type') || '',
                            method: item.effectiveMethod
                        });
                    }

                    if (classification.state === 'success') {
                        await queue.remove(item.id);
                        results.push({ id: item.id, state: 'success' });
                        continue;
                    }

                    if (classification.state === 'auth') {
                        await queue.update(item.id, { status: 'failed', errorMessage: t('sign_in_again', 'Please sign in again to continue syncing.') });
                        if (window.SP && typeof window.SP.toast === 'function') {
                            window.SP.toast(t('sign_in_again', 'Please sign in again to continue syncing.'), 'warning');
                        }
                        results.push({ id: item.id, state: 'auth' });
                        break;
                    }

                    if (classification.state === 'retry') {
                        await markRetry(item, Number(item.attempts || 0) + 1);
                        results.push({ id: item.id, state: 'retry' });
                        continue;
                    }

                    await markFailed(item, classification.message);
                    results.push({ id: item.id, state: 'failed' });
                } catch (error) {
                    if (error && error.code === 'auth') {
                        await queue.update(item.id, { status: 'failed', errorMessage: t('sign_in_again', 'Please sign in again to continue syncing.') });
                        if (window.SP && typeof window.SP.toast === 'function') {
                            window.SP.toast(t('sign_in_again', 'Please sign in again to continue syncing.'), 'warning');
                        }
                        results.push({ id: item.id, state: 'auth' });
                        break;
                    }

                    var online = await probeConnectivity(true);
                    if (!online) {
                        await markRetry(item, Number(item.attempts || 0) + 1);
                        results.push({ id: item.id, state: 'retry' });
                        break;
                    }

                    await markFailed(item, t('failed_generic', 'This item needs attention.'));
                    results.push({ id: item.id, state: 'failed' });
                } finally {
                    emitChange();
                }
            }

            state.syncing = false;
            emitChange();
            window.dispatchEvent(new CustomEvent('spoffline:sync-complete', { detail: results }));
            await warmCache({ afterSync: true });
            return results;
        })();

        try {
            return await syncPromise;
        } finally {
            syncPromise = null;
        }
    }

    async function pendingItems() {
        await ready;

        if (!queue) {
            return [];
        }

        return queue.list(userId);
    }

    async function pendingCount() {
        await ready;
        var items = await pendingItems();
        return items.length;
    }

    async function discardItem(id) {
        await ready;
        if (!queue) {
            return;
        }
        await queue.remove(id);
        emitChange();
    }

    async function retryItem(id) {
        await ready;
        if (!queue) {
            return [];
        }
        await queue.update(id, {
            status: 'pending',
            nextAttemptAt: Date.now(),
            errorMessage: ''
        });
        emitChange();
        return syncAll();
    }

    function formatBytes(bytes) {
        if (!bytes) {
            return '0 KB';
        }

        if (bytes >= 1024 * 1024) {
            return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
        }

        return Math.max(1, Math.round(bytes / 1024)) + ' KB';
    }

    async function estimateStorage() {
        await ready;

        if (!navigator.storage || typeof navigator.storage.estimate !== 'function') {
            return null;
        }

        var result = await navigator.storage.estimate();
        return {
            usage: result.usage || 0,
            quota: result.quota || 0,
            usageLabel: formatBytes(result.usage || 0),
            quotaLabel: formatBytes(result.quota || 0)
        };
    }

    function collectWarmUrls() {
        var urls = [];
        var seen = {};

        Array.prototype.slice.call(document.querySelectorAll('aside a[href]')).forEach(function (link) {
            try {
                var url = buildItemUrl(link.href);
                if (url.origin !== window.location.origin || !shared.shouldWarmPath(url.pathname)) {
                    return;
                }
                if (seen[url.pathname + url.search]) {
                    return;
                }

                seen[url.pathname + url.search] = true;
                urls.push(url.pathname + url.search);
            } catch (error) {
                // Ignore malformed links.
            }
        });

        ['/offline', '/offline/queue', '/dashboard'].forEach(function (fixed) {
            if (!seen[fixed]) {
                urls.unshift(fixed);
                seen[fixed] = true;
            }
        });

        return urls.slice(0, 30);
    }

    function canWarmAutomatically() {
        var connection = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
        if (connection && (connection.saveData || /(^2g$|slow-2g)/i.test(String(connection.effectiveType || '')))) {
            return false;
        }

        return true;
    }

    function postMessageToServiceWorker(message) {
        return new Promise(function (resolve, reject) {
            if (!navigator.serviceWorker || !navigator.serviceWorker.controller) {
                reject(new Error('sw'));
                return;
            }

            var channel = new MessageChannel();
            channel.port1.onmessage = function (event) {
                resolve(event.data);
            };

            navigator.serviceWorker.controller.postMessage(message, [channel.port2]);
        });
    }

    async function getAvailablePages() {
        await ready;

        try {
            var result = await postMessageToServiceWorker({ type: 'SP_LIST_CACHED_PAGES' });
            return Array.isArray(result && result.pages) ? result.pages : [];
        } catch (error) {
            return [];
        }
    }

    async function warmCache(options) {
        await ready;

        options = options || {};
        if (!userId || !navigator.serviceWorker || !navigator.serviceWorker.controller) {
            return null;
        }

        if (!options.manual && !options.afterSync && !canWarmAutomatically()) {
            return null;
        }

        var urls = collectWarmUrls();
        if (!urls.length) {
            return null;
        }

        var requestId = generateId();
        state.warmProgress = {
            requestId: requestId,
            total: urls.length,
            done: 0,
            manual: !!options.manual
        };
        emitChange();

        return new Promise(function (resolve, reject) {
            warmResolvers[requestId] = { resolve: resolve, reject: reject };
            navigator.serviceWorker.controller.postMessage({
                type: 'SP_WARM_CACHE',
                urls: urls,
                requestId: requestId
            });
        });
    }

    function queueStorageFallback() {
        store = shared.createMemoryStore();
        queue = shared.createQueueManager({ storage: store });
        state.queueAvailable = false;

        if (window.SP && typeof window.SP.toast === 'function') {
            window.SP.toast(t('memory_fallback', 'Offline queue storage is limited in this browser session.'), 'warning');
        }
    }

    function openIndexedDbStore() {
        return new Promise(function (resolve, reject) {
            if (!window.indexedDB) {
                reject(new Error('indexeddb'));
                return;
            }

            var request = window.indexedDB.open('sp_offline_v10', 1);
            request.onupgradeneeded = function (event) {
                var db = event.target.result;
                if (!db.objectStoreNames.contains('queue')) {
                    var objectStore = db.createObjectStore('queue', { keyPath: 'id' });
                    objectStore.createIndex('userId', 'userId', { unique: false });
                    objectStore.createIndex('createdAt', 'createdAt', { unique: false });
                }
            };
            request.onerror = function () {
                reject(request.error || new Error('indexeddb'));
            };
            request.onsuccess = function () {
                var db = request.result;
                resolve({
                    async all() {
                        return await new Promise(function (done, fail) {
                            var tx = db.transaction('queue', 'readonly');
                            var req = tx.objectStore('queue').getAll();
                            req.onsuccess = function () { done(req.result || []); };
                            req.onerror = function () { fail(req.error); };
                        });
                    },
                    async get(id) {
                        return await new Promise(function (done, fail) {
                            var tx = db.transaction('queue', 'readonly');
                            var req = tx.objectStore('queue').get(id);
                            req.onsuccess = function () { done(req.result || null); };
                            req.onerror = function () { fail(req.error); };
                        });
                    },
                    async set(id, value) {
                        await new Promise(function (done, fail) {
                            var tx = db.transaction('queue', 'readwrite');
                            tx.objectStore('queue').put(value);
                            tx.oncomplete = function () { done(); };
                            tx.onerror = function () { fail(tx.error); };
                        });
                    },
                    async delete(id) {
                        await new Promise(function (done, fail) {
                            var tx = db.transaction('queue', 'readwrite');
                            tx.objectStore('queue').delete(id);
                            tx.oncomplete = function () { done(); };
                            tx.onerror = function () { fail(tx.error); };
                        });
                    }
                });
            };
        });
    }

    function installFormInterceptor() {
        document.addEventListener('click', function (event) {
            var button = event.target && event.target.closest ? event.target.closest('button, input[type="submit"], input[type="image"]') : null;
            window.__spOfflineLastSubmitter = button || null;
        }, true);

        document.addEventListener('submit', async function (event) {
            var form = event.target;
            var submitter = event.submitter || window.__spOfflineLastSubmitter || null;
            if (event.defaultPrevented || !(form instanceof HTMLFormElement) || shouldIgnoreForm(form, submitter)) {
                return;
            }

            var methods = formMethods(form, submitter);
            if (!isSameOrigin(methods.action)) {
                return;
            }

            if (methods.effectiveMethod === 'GET' || !shared.shouldQueueMutation(buildItemUrl(methods.action).pathname)) {
                return;
            }

            if (navigator.onLine && state.online !== false) {
                return;
            }

            event.preventDefault();
            event.stopImmediatePropagation();
            await queueFormSubmission(form, submitter);
        }, true);
    }

    function handleWorkerMessage(event) {
        var data = event.data || {};
        if (data.type === 'SP_WARM_PROGRESS' && state.warmProgress && data.requestId === state.warmProgress.requestId) {
            state.warmProgress.done = data.done;
            state.warmProgress.total = data.total;
            emitChange();
            return;
        }

        if (data.type === 'SP_WARM_COMPLETE' && state.warmProgress && data.requestId === state.warmProgress.requestId) {
            state.lastWarmAt = data.finishedAt || new Date().toISOString();
            state.warmProgress = null;
            try {
                localStorage.setItem('sp-offline-last-warm:' + userId, state.lastWarmAt);
            } catch (error) {
                // Ignore localStorage failures.
            }
            emitChange();
            if (warmResolvers[data.requestId]) {
                warmResolvers[data.requestId].resolve(data);
                delete warmResolvers[data.requestId];
            }
            return;
        }
    }

    function isOffline() {
        return !state.online;
    }

    async function initialize() {
        try {
            if (!userId) {
                readyResolved = true;
                readyResolve();
                window.dispatchEvent(new CustomEvent('spoffline:ready'));
                emitChange();
                return;
            }

            try {
                store = await openIndexedDbStore();
                queue = shared.createQueueManager({ storage: store });
            } catch (error) {
                queueStorageFallback();
            }

            try {
                state.lastWarmAt = localStorage.getItem('sp-offline-last-warm:' + userId);
            } catch (error) {
                state.lastWarmAt = null;
            }

            installFormInterceptor();
            installFetchInterceptor();
            installAxiosInterceptor();
            await probeConnectivity(true);

            window.addEventListener('online', function () {
                probeConnectivity(true).then(function (online) {
                    if (online) {
                        syncAll();
                    }
                });
            });
            window.addEventListener('offline', function () {
                state.online = false;
                setOfflineBanner(true);
                emitChange();
            });

            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.addEventListener('message', handleWorkerMessage);
            }

            var autoWarm = false;
            if (state.lastWarmAt) {
                autoWarm = (Date.now() - new Date(state.lastWarmAt).getTime()) > 24 * 60 * 60 * 1000;
            } else {
                autoWarm = true;
            }

            readyResolved = true;
            readyResolve();
            window.dispatchEvent(new CustomEvent('spoffline:ready'));

            if (autoWarm && canWarmAutomatically()) {
                var schedule = window.requestIdleCallback || function (callback) {
                    return setTimeout(callback, 1200);
                };

                schedule(function () {
                    warmCache({ automatic: true });
                });
            }

            emitChange();
        } catch (error) {
            if (!readyResolved) {
                readyReject(error);
            }
            throw error;
        }
    }

    window.SPOffline = {
        ready: ready,
        clearUserData: shared.createReadyMethod(ready, async function (targetUserId) {
            if (!store) {
                return;
            }

            var wantedUserId = targetUserId === undefined || targetUserId === null ? userId : targetUserId;
            var items = await store.all();
            await Promise.all(items.filter(function (item) {
                return String(item.userId) === String(wantedUserId);
            }).map(function (item) {
                return store.delete(item.id);
            }));
            emitChange();
        }),
        discardItem: shared.createReadyMethod(ready, discardItem),
        estimateStorage: shared.createReadyMethod(ready, estimateStorage, null),
        getAvailablePages: shared.createReadyMethod(ready, getAvailablePages, []),
        getState: shared.createReadyMethod(ready, function () { return Object.assign({}, state); }, function () { return Object.assign({}, state); }),
        isOffline: shared.createReadyMethod(ready, isOffline, function () { return !state.online; }),
        onChange: onChange,
        pendingCount: shared.createReadyMethod(ready, pendingCount, 0),
        pendingItems: shared.createReadyMethod(ready, pendingItems, []),
        retryItem: shared.createReadyMethod(ready, retryItem, []),
        syncAll: shared.createReadyMethod(ready, syncAll, []),
        warmCache: shared.createReadyMethod(ready, warmCache, null)
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize, { once: true });
    } else {
        initialize();
    }
})();
