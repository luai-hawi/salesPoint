(function () {
    'use strict';

    if (!window.SPOffline) {
        return;
    }

    var translations = window.SPOfflineTranslations || {};
    var elements = {};

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

    function create(tag, className, text) {
        var node = document.createElement(tag);
        if (className) {
            node.className = className;
        }
        if (text !== undefined && text !== null) {
            node.textContent = text;
        }
        return node;
    }

    function formatDate(value) {
        if (!value) {
            return '—';
        }

        try {
            return new Intl.DateTimeFormat(document.documentElement.lang || 'en', {
                dateStyle: 'medium',
                timeStyle: 'short'
            }).format(new Date(value));
        } catch (error) {
            return String(value);
        }
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

    function statusLabel(status) {
        var map = {
            pending: t('status_pending', 'Pending'),
            failed: t('status_failed', 'Failed'),
            syncing: t('status_syncing', 'Syncing')
        };

        return map[status] || status;
    }

    function statusTone(status) {
        var map = {
            pending: 'bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-200',
            failed: 'bg-red-50 text-red-700 ring-1 ring-inset ring-red-200',
            syncing: 'bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-200'
        };

        return map[status] || 'bg-gray-50 text-gray-700 ring-1 ring-inset ring-gray-200';
    }

    function buildShell() {
        if (elements.button) {
            return;
        }

        elements.button = create('button', 'fixed bottom-24 end-6 z-[9989] hidden items-center gap-2 rounded-full bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-xl hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2');
        elements.button.type = 'button';
        elements.button.setAttribute('aria-haspopup', 'dialog');
        elements.button.addEventListener('click', function () {
            toggleDrawer(true);
        });

        var icon = create('span', 'inline-flex h-8 w-8 items-center justify-center rounded-full bg-white/15');
        icon.innerHTML = '<svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12a7 7 0 0113.872-1H20a3 3 0 010 6h-2.2A7.001 7.001 0 015 12zm7-2v6m0 0l-3-3m3 3l3-3"/></svg>';
        elements.button.appendChild(icon);
        elements.button.appendChild(create('span', '', t('sync_center', 'Sync center')));
        elements.badge = create('span', 'inline-flex min-h-5 min-w-5 items-center justify-center rounded-full bg-white px-1.5 text-xs font-bold text-indigo-600', '0');
        elements.button.appendChild(elements.badge);
        document.body.appendChild(elements.button);

        elements.drawer = create('div', 'fixed inset-0 z-[9995] hidden');
        elements.drawer.innerHTML = '' +
            '<div class="absolute inset-0 bg-gray-900/40"></div>' +
            '<div class="absolute inset-y-0 end-0 flex w-full max-w-xl">' +
            '  <section class="ms-auto flex h-full w-full max-w-xl flex-col bg-white shadow-2xl" role="dialog" aria-modal="true" aria-labelledby="sp-sync-center-title">' +
            '    <header class="flex items-center justify-between border-b border-gray-200 px-4 py-4 sm:px-6">' +
            '      <div><h2 id="sp-sync-center-title" class="text-lg font-semibold text-gray-900"></h2><p class="mt-1 text-sm text-gray-500" id="sp-sync-center-subtitle"></p></div>' +
            '      <button type="button" class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50" data-close>' + t('close', 'Close') + '</button>' +
            '    </header>' +
            '    <div class="flex-1 overflow-y-auto px-4 py-4 sm:px-6">' +
            '      <div class="space-y-6" id="sp-sync-center-body"></div>' +
            '    </div>' +
            '  </section>' +
            '</div>';
        document.body.appendChild(elements.drawer);
        elements.drawer.querySelector('[data-close]').addEventListener('click', function () {
            toggleDrawer(false);
        });
        elements.drawer.firstElementChild.addEventListener('click', function () {
            toggleDrawer(false);
        });
        elements.title = elements.drawer.querySelector('#sp-sync-center-title');
        elements.subtitle = elements.drawer.querySelector('#sp-sync-center-subtitle');
        elements.body = elements.drawer.querySelector('#sp-sync-center-body');

        var pageRoot = document.getElementById('offline-queue-page');
        if (pageRoot) {
            elements.pageRoot = pageRoot;
        }
    }

    function toggleDrawer(open) {
        elements.drawer.classList.toggle('hidden', !open);
        document.body.classList.toggle('overflow-hidden', !!open);
    }

    function renderAvailablePages(container, pages) {
        container.innerHTML = '';
        if (!pages.length) {
            container.appendChild(create('p', 'text-sm text-gray-500', t('no_cached_pages', 'No pages have been downloaded yet.')));
            return;
        }

        var list = create('div', 'grid gap-3 sm:grid-cols-2');
        pages.forEach(function (page) {
            var card = create('a', 'rounded-xl border border-gray-200 bg-white p-4 shadow-sm hover:border-indigo-300 hover:bg-indigo-50/40');
            card.href = page.url;
            var title = create('div', 'text-sm font-semibold text-gray-900', page.label || page.url);
            var path = create('div', 'mt-1 text-xs text-gray-500', page.url);
            card.appendChild(title);
            card.appendChild(path);
            list.appendChild(card);
        });
        container.appendChild(list);
    }

    async function renderPageRoot(pending, state, pages) {
        if (!elements.pageRoot) {
            return;
        }

        var mode = elements.pageRoot.getAttribute('data-mode') || 'queue';
        elements.pageRoot.innerHTML = '';

        if (mode === 'overview') {
            var pagesBlock = create('section', 'rounded-xl border border-gray-200 bg-white p-4 shadow-sm');
            pagesBlock.appendChild(create('h3', 'text-sm font-semibold text-gray-900', t('available_offline_pages', 'Available offline pages')));
            var pagesHost = create('div', 'mt-3');
            pagesBlock.appendChild(pagesHost);
            renderAvailablePages(pagesHost, pages);
            elements.pageRoot.appendChild(pagesBlock);
            return;
        }

        var actionBar = create('div', 'flex flex-wrap gap-2');
        var syncButton = create('button', 'rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700', t('sync_now', 'Sync now'));
        syncButton.type = 'button';
        syncButton.disabled = state.syncing || pending.length === 0;
        syncButton.addEventListener('click', async function () {
            await window.SPOffline.syncAll();
            render();
        });
        actionBar.appendChild(syncButton);

        var warmButton = create('button', 'rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50', t('download_offline', 'Download for offline use'));
        warmButton.type = 'button';
        warmButton.disabled = !!state.warmProgress;
        warmButton.addEventListener('click', async function () {
            await window.SPOffline.warmCache({ manual: true });
            render();
        });
        actionBar.appendChild(warmButton);
        elements.pageRoot.appendChild(actionBar);

        var queueCard = create('section', 'rounded-xl border border-gray-200 bg-white p-4 shadow-sm');
        queueCard.appendChild(create('h3', 'text-sm font-semibold text-gray-900', t('pending_items', 'Pending items')));
        if (!pending.length) {
            queueCard.appendChild(create('p', 'mt-3 text-sm text-gray-500', t('queue_empty', 'No pending offline items.')));
        } else {
            pending.forEach(function (item) {
                var line = create('div', 'mt-3 rounded-xl border border-gray-200 p-3');
                line.appendChild(create('div', 'text-sm font-semibold text-gray-900', item.label || item.targetUrl));
                line.appendChild(create('div', 'mt-1 text-xs text-gray-500', statusLabel(item.status)));
                if (item.errorMessage) {
                    line.appendChild(create('div', 'mt-1 text-xs text-red-700', item.errorMessage));
                }
                queueCard.appendChild(line);
            });
        }
        elements.pageRoot.appendChild(queueCard);

        var pagesCard = create('section', 'rounded-xl border border-gray-200 bg-white p-4 shadow-sm');
        pagesCard.appendChild(create('h3', 'text-sm font-semibold text-gray-900', t('available_offline_pages', 'Available offline pages')));
        var pagesHost = create('div', 'mt-3');
        pagesCard.appendChild(pagesHost);
        renderAvailablePages(pagesHost, pages);
        elements.pageRoot.appendChild(pagesCard);
    }

    async function render() {
        if (window.SPOffline && window.SPOffline.ready) {
            try {
                await window.SPOffline.ready;
            } catch (error) {
                // Render safe empty state when initialization fails.
            }
        }

        buildShell();
        var pending = await window.SPOffline.pendingItems();
        var count = pending.length;
        var state = window.SPOffline.getState();
        var storage = await window.SPOffline.estimateStorage();

        elements.button.classList.toggle('hidden', count === 0 && !window.SPOffline.isOffline());
        elements.button.classList.toggle('inline-flex', count > 0 || window.SPOffline.isOffline());
        elements.badge.textContent = String(count);

        elements.title.textContent = t('sync_center', 'Sync center');
        elements.subtitle.textContent = window.SPOffline.isOffline()
            ? t('offline_state', 'Offline — changes will queue until the connection returns.')
            : t('online_state', 'Online — pending work will sync automatically.');

        elements.body.innerHTML = '';

        var summary = create('section', 'rounded-xl border border-gray-200 bg-white p-4 shadow-sm');
        summary.appendChild(create('h3', 'text-sm font-semibold text-gray-900', t('connection_status', 'Connection status')));
        summary.appendChild(create('p', 'mt-2 text-sm text-gray-600', window.SPOffline.isOffline() ? t('offline_state', 'Offline — changes will queue until the connection returns.') : t('online_state', 'Online — pending work will sync automatically.')));
        summary.appendChild(create('p', 'mt-2 text-xs text-gray-500', t('last_warm', 'Last offline download: :time', {
            time: state.lastWarmAt ? formatDate(state.lastWarmAt) : t('never', 'Never')
        })));
        if (storage) {
            summary.appendChild(create('p', 'mt-2 text-xs text-gray-500', t('storage_usage', 'Storage usage: :usage of :quota', {
                usage: storage.usageLabel,
                quota: storage.quotaLabel
            })));
        }
        if (state.warmProgress) {
            summary.appendChild(create('p', 'mt-2 text-xs font-medium text-indigo-600', t('warm_progress', 'Downloading pages for offline use (:done / :total)', {
                done: state.warmProgress.done,
                total: state.warmProgress.total
            })));
        }
        elements.body.appendChild(summary);

        var actions = create('section', 'rounded-xl border border-gray-200 bg-white p-4 shadow-sm');
        actions.appendChild(create('h3', 'text-sm font-semibold text-gray-900', t('actions', 'Actions')));
        var actionBar = create('div', 'mt-3 flex flex-wrap gap-2');
        var syncButton = create('button', 'rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700');
        syncButton.type = 'button';
        syncButton.textContent = t('sync_now', 'Sync now');
        syncButton.disabled = state.syncing || count === 0;
        syncButton.addEventListener('click', async function () {
            await window.SPOffline.syncAll();
            render();
        });
        actionBar.appendChild(syncButton);

        var warmButton = create('button', 'rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50', t('download_offline', 'Download for offline use'));
        warmButton.type = 'button';
        warmButton.disabled = !!state.warmProgress;
        warmButton.addEventListener('click', async function () {
            await window.SPOffline.warmCache({ manual: true });
            render();
        });
        actionBar.appendChild(warmButton);
        actions.appendChild(actionBar);
        elements.body.appendChild(actions);

        var itemsSection = create('section', 'rounded-xl border border-gray-200 bg-white p-4 shadow-sm');
        itemsSection.appendChild(create('h3', 'text-sm font-semibold text-gray-900', t('pending_items', 'Pending items')));
        if (!count) {
            itemsSection.appendChild(create('p', 'mt-3 text-sm text-gray-500', t('queue_empty', 'No pending offline items.')));
        } else {
            var list = create('div', 'mt-3 space-y-3');
            pending.forEach(function (item) {
                var row = create('div', 'rounded-xl border border-gray-200 p-4');
                var header = create('div', 'flex flex-wrap items-start justify-between gap-3');
                var info = create('div', 'min-w-0');
                info.appendChild(create('div', 'text-sm font-semibold text-gray-900', item.label || item.targetUrl));
                info.appendChild(create('div', 'mt-1 text-xs text-gray-500', item.pageUrl || item.targetUrl));
                info.appendChild(create('div', 'mt-1 text-xs text-gray-500', t('item_meta', 'Queued :time • :size • attempts: :attempts', {
                    time: formatDate(item.createdAt),
                    size: formatBytes(item.size || 0),
                    attempts: item.attempts || 0
                })));
                header.appendChild(info);
                var status = create('span', 'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ' + statusTone(item.status), statusLabel(item.status));
                header.appendChild(status);
                row.appendChild(header);
                if (item.errorMessage) {
                    row.appendChild(create('p', 'mt-3 text-sm text-red-700', item.errorMessage));
                }

                var itemActions = create('div', 'mt-3 flex flex-wrap gap-2');
                var retry = create('button', 'rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50', t('retry', 'Retry'));
                retry.type = 'button';
                retry.disabled = item.status === 'syncing';
                retry.addEventListener('click', async function () {
                    await window.SPOffline.retryItem(item.id);
                    render();
                });
                itemActions.appendChild(retry);

                var discard = create('button', 'rounded-lg bg-red-600 px-3 py-2 text-sm font-semibold text-white hover:bg-red-700', t('discard', 'Discard'));
                discard.type = 'button';
                discard.addEventListener('click', async function () {
                    var ok = true;
                    if (window.SP && typeof window.SP.confirm === 'function') {
                        ok = await window.SP.confirm({
                            title: t('discard', 'Discard'),
                            message: t('discard_confirm', 'Discard this queued change?'),
                            confirmText: t('discard', 'Discard'),
                            danger: true
                        });
                    }
                    if (!ok) {
                        return;
                    }
                    await window.SPOffline.discardItem(item.id);
                    render();
                });
                itemActions.appendChild(discard);

                if (item.pageUrl) {
                    var edit = create('a', 'rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50', t('edit', 'Edit'));
                    edit.href = item.pageUrl;
                    itemActions.appendChild(edit);
                }

                row.appendChild(itemActions);
                list.appendChild(row);
            });
            itemsSection.appendChild(list);
        }
        elements.body.appendChild(itemsSection);

        var help = create('section', 'rounded-xl border border-gray-200 bg-white p-4 shadow-sm');
        help.appendChild(create('h3', 'text-sm font-semibold text-gray-900', t('help_title', 'Offline help')));
        help.appendChild(create('p', 'mt-2 text-sm text-gray-600', t('help_body', 'Queued changes sync in the order they were created. If a later item depends on a record created offline earlier, the server may not be able to resolve that dependency until you fix it manually.')));
        elements.body.appendChild(help);

        var pages = elements.pageRoot ? await window.SPOffline.getAvailablePages() : [];
        await renderPageRoot(pending, state, pages);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', render, { once: true });
    } else {
        render();
    }

    window.addEventListener('spoffline:ready', function () {
        render();
    });

    window.SPOffline.onChange(render);
})();
