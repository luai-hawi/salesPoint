(function (root, factory) {
    if (typeof module !== 'undefined' && module.exports) {
        module.exports = factory();
        return;
    }

    root.SPOfflineShared = factory();
})(typeof globalThis !== 'undefined' ? globalThis : (typeof self !== 'undefined' ? self : this), function () {
    'use strict';

    var MUTATION_SKIP_PATTERNS = [
        /^\/(?:login|logout)(?:\/|$)/i,
        /^\/password(?:\/|$)/i,
        /^\/profile(?:\/|$)/i,
        /^\/admin(?:\/|$)/i,
        /^\/staff(?:\/|$)/i,
        /^\/offline\/sync(?:\/|$)/i,
        /^\/bills(?:\/|$)/i,
        /^\/customers\/\d+\/payments(?:\/|$)/i,
        /^\/installments\/from-bill(?:\/|$)/i
    ];

    var SAFE_READ_PATTERNS = [
        /^\/api\/(?:tags|categories|active-sales)(?:\?|$)/i,
        /^\/api\/(?:suppliers\/search|purchase-bills\/search|customers\/search|employees\/search|suppliers\/search-payment)(?:\?|$)/i,
        /^\/products\/(?:search|searchAll|searchWithoutBarcode|search-barcode|get-suppliers|categories|next-id)(?:\?|$)/i,
        /^\/products\/imei\/(?:check|search)(?:\?|$)/i,
        /^\/products\/\d+\/imeis(?:\/available)?(?:\?|$)/i,
        /^\/customers\/\d+\/recent-payments(?:\?|$)/i,
        /^\/bills\/quick-stats(?:\?|$)/i
    ];

    var NAVIGATION_SKIP_PATTERNS = [
        /^\/(?:login|logout)(?:\/|$)/i,
        /^\/auth(?:\/|$)/i,
        /^\/up(?:\?|$)/i,
        /^\/admin(?:\/|$)/i,
        /^\/staff(?:\/|$)/i,
        /^\/password(?:\/|$)/i,
        /^\/profile(?:\/|$)/i
    ];

    var WARM_SKIP_PATTERNS = [
        /^\/(?:logout|login)(?:\/|$)/i,
        /^\/lang(?:\/|$)/i,
        /^\/admin(?:\/|$)/i,
        /^\/staff(?:\/|$)/i
    ];

    function normalizePath(value) {
        if (!value) {
            return '/';
        }

        try {
            var url = new URL(value, 'https://salespoint.local');
            return url.pathname || '/';
        } catch (error) {
            return value.charAt(0) === '/' ? value : '/' + value;
        }
    }

    function matches(path, patterns) {
        path = normalizePath(path);

        for (var i = 0; i < patterns.length; i += 1) {
            if (patterns[i].test(path)) {
                return true;
            }
        }

        return false;
    }

    function shouldQueueMutation(url) {
        return !matches(url, MUTATION_SKIP_PATTERNS);
    }

    function isSafeReadPath(url) {
        return matches(url, SAFE_READ_PATTERNS);
    }

    function shouldHandleNavigation(url) {
        return !matches(url, NAVIGATION_SKIP_PATTERNS);
    }

    function shouldWarmPath(url) {
        return !matches(url, WARM_SKIP_PATTERNS);
    }

    function buildPartitionName(kind, userId, version) {
        return 'sp-' + kind + '-' + (version || 'v10') + '-u-' + String(userId || 'guest');
    }

    function titleizeSegment(segment) {
        return String(segment || '')
            .replace(/[-_]+/g, ' ')
            .replace(/\b\w/g, function (char) {
                return char.toUpperCase();
            });
    }

    function prettyPathLabel(url) {
        var path = normalizePath(url);
        var segments = path.split('/').filter(Boolean).slice(0, 3).map(function (segment) {
            return /^\d+$/.test(segment) ? '#' : titleizeSegment(segment);
        });

        return segments.length ? segments.join(' / ') : '/';
    }

    function deriveOfflineLabel(options) {
        options = options || {};
        if (options.label) {
            return String(options.label).trim();
        }

        if (options.titleText) {
            return String(options.titleText).trim();
        }

        return prettyPathLabel(options.url || '/');
    }

    function extractErrorMessage(bodyText, contentType) {
        if (!bodyText) {
            return '';
        }

        var normalizedType = String(contentType || '').toLowerCase();
        if (normalizedType.indexOf('json') !== -1) {
            try {
                var parsed = JSON.parse(bodyText);
                if (parsed && parsed.message) {
                    return String(parsed.message);
                }
                if (parsed && parsed.errors) {
                    var parts = [];
                    Object.keys(parsed.errors).forEach(function (key) {
                        var value = parsed.errors[key];
                        if (Array.isArray(value)) {
                            parts = parts.concat(value);
                        }
                    });
                    if (parts.length) {
                        return parts.join(' ');
                    }
                }
            } catch (error) {
                return '';
            }
        }

        var listMatches = bodyText.match(/<li[^>]*>(.*?)<\/li>/gi);
        if (listMatches && listMatches.length) {
            return listMatches.map(stripHtml).join(' ');
        }

        return stripHtml(bodyText).slice(0, 500);
    }

    function stripHtml(value) {
        return String(value || '')
            .replace(/<script[\s\S]*?<\/script>/gi, ' ')
            .replace(/<style[\s\S]*?<\/style>/gi, ' ')
            .replace(/<[^>]+>/g, ' ')
            .replace(/\s+/g, ' ')
            .trim();
    }

    function classifyReplayResult(details) {
        details = details || {};
        var status = Number(details.status || 0);
        var method = String(details.method || '').toUpperCase();
        var location = String(details.location || '');
        var finalUrl = String(details.finalUrl || '');

        if (details.networkError || status >= 500 || status === 0) {
            return { state: 'retry' };
        }

        if (status === 419) {
            return { state: 'refresh-csrf' };
        }

        if (status === 401 || location.indexOf('/login') !== -1 || finalUrl.indexOf('/login') !== -1) {
            return { state: 'auth' };
        }

        if (status === 404 && method === 'DELETE') {
            return { state: 'success' };
        }

        if ((status >= 200 && status < 300) || (status >= 300 && status < 400)) {
            return { state: 'success' };
        }

        if (status === 409 || status === 422) {
            return {
                state: 'failed',
                message: extractErrorMessage(details.bodyText, details.contentType)
            };
        }

        return {
            state: 'failed',
            message: extractErrorMessage(details.bodyText, details.contentType)
        };
    }

    function computeBackoffMs(attempts, baseMs, maxMs) {
        attempts = Math.max(1, Number(attempts || 1));
        baseMs = Number(baseMs || 5000);
        maxMs = Number(maxMs || 300000);

        return Math.min(baseMs * Math.pow(2, attempts - 1), maxMs);
    }

    function estimatePayloadSize(payload) {
        if (payload === null || payload === undefined) {
            return 0;
        }

        if (typeof Blob !== 'undefined' && payload instanceof Blob) {
            return payload.size;
        }

        if (Array.isArray(payload)) {
            return payload.reduce(function (sum, value) {
                return sum + estimatePayloadSize(value);
            }, 0);
        }

        if (typeof payload === 'object') {
            if (payload.__blob === true) {
                return Number(payload.size || 0);
            }

            return estimatePayloadSize(JSON.stringify(payload));
        }

        return String(payload).length;
    }

    function createMemoryStore() {
        var map = new Map();

        return {
            async all() {
                return Array.from(map.values());
            },
            async get(id) {
                return map.has(id) ? map.get(id) : null;
            },
            async set(id, value) {
                map.set(id, value);
            },
            async delete(id) {
                map.delete(id);
            }
        };
    }

    function createQueueManager(options) {
        options = options || {};
        var storage = options.storage;
        var now = options.now || function () { return Date.now(); };

        function sortItems(items) {
            return items.sort(function (left, right) {
                if (left.createdAt === right.createdAt) {
                    return String(left.id).localeCompare(String(right.id));
                }

                return Number(left.createdAt || 0) - Number(right.createdAt || 0);
            });
        }

        return {
            async enqueue(item) {
                var stored = Object.assign({
                    attempts: 0,
                    status: 'pending',
                    createdAt: now(),
                    nextAttemptAt: now()
                }, item);
                await storage.set(stored.id, stored);
                return stored;
            },
            async list(userId) {
                var items = await storage.all();
                return sortItems(items.filter(function (item) {
                    return userId === undefined || item.userId === userId;
                }));
            },
            async update(id, patch) {
                var item = await storage.get(id);
                if (!item) {
                    return null;
                }

                var next = Object.assign({}, item, patch);
                await storage.set(id, next);
                return next;
            },
            async remove(id) {
                await storage.delete(id);
            }
        };
    }

    function createReadyMethod(ready, callback, fallbackValue) {
        return async function () {
            try {
                if (ready && typeof ready.then === 'function') {
                    await ready;
                }
            } catch (error) {
                if (fallbackValue !== undefined) {
                    return typeof fallbackValue === 'function'
                        ? fallbackValue.apply(null, arguments)
                        : fallbackValue;
                }
                throw error;
            }

            return callback.apply(null, arguments);
        };
    }

    return {
        buildPartitionName: buildPartitionName,
        classifyReplayResult: classifyReplayResult,
        createReadyMethod: createReadyMethod,
        computeBackoffMs: computeBackoffMs,
        createMemoryStore: createMemoryStore,
        createQueueManager: createQueueManager,
        deriveOfflineLabel: deriveOfflineLabel,
        estimatePayloadSize: estimatePayloadSize,
        extractErrorMessage: extractErrorMessage,
        isSafeReadPath: isSafeReadPath,
        normalizePath: normalizePath,
        prettyPathLabel: prettyPathLabel,
        shouldHandleNavigation: shouldHandleNavigation,
        shouldQueueMutation: shouldQueueMutation,
        shouldWarmPath: shouldWarmPath
    };
});
