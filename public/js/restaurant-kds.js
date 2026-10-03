(function () {
    const root = document.getElementById('restaurant-kds');
    if (!root) {
        return;
    }

    const config = JSON.parse(root.dataset.config || '{}');
    const columns = {
        new: document.getElementById('kds-column-new'),
        preparing: document.getElementById('kds-column-preparing'),
        ready: document.getElementById('kds-column-ready'),
        served: document.getElementById('kds-column-served'),
    };
    const stationFilter = document.getElementById('kds-station-filter');
    const soundBanner = document.getElementById('kds-sound-banner');
    const connectionBanner = document.getElementById('kds-connection-banner');
    const authBanner = document.getElementById('kds-auth-banner');
    const soundToggle = document.getElementById('kds-sound-toggle');
    const themeToggle = document.getElementById('kds-theme-toggle');
    const fullscreenToggle = document.getElementById('kds-fullscreen-toggle');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    const state = {
        cursor: null,
        etag: null,
        ticketMap: new Map(),
        inFlight: false,
        timerId: null,
        baseDelay: (config.pollSeconds || 4) * 1000,
        delay: (config.pollSeconds || 4) * 1000,
        muted: true,
        unlocked: false,
        wakeLock: null,
    };

    function text(value) {
        return String(value ?? '');
    }

    function beep() {
        if (state.muted || !state.unlocked || !window.AudioContext) {
            return;
        }

        const context = new AudioContext();
        const oscillator = context.createOscillator();
        const gain = context.createGain();
        oscillator.type = 'square';
        oscillator.frequency.value = 880;
        gain.gain.value = 0.04;
        oscillator.connect(gain);
        gain.connect(context.destination);
        oscillator.start();
        oscillator.stop(context.currentTime + 0.2);
    }

    async function requestWakeLock() {
        if (!('wakeLock' in navigator) || state.wakeLock) {
            return;
        }

        try {
            state.wakeLock = await navigator.wakeLock.request('screen');
        } catch (error) {
            state.wakeLock = null;
        }
    }

    function unlockSound() {
        state.unlocked = true;
        state.muted = false;
        soundBanner.classList.add('hidden');
        soundToggle.textContent = config.translations.mute;
        requestWakeLock();
    }

    function elapsedClass(minutes) {
        if (minutes >= config.thresholds.red) return ['bg-red-500', 'text-white'];
        if (minutes >= config.thresholds.amber) return ['bg-amber-400', 'text-slate-900'];
        return ['bg-emerald-500', 'text-white'];
    }

    function diffMinutes(sentAt) {
        return Math.max(0, Math.floor((Date.now() - new Date(sentAt).getTime()) / 60000));
    }

    function el(tag, className, value) {
        const node = document.createElement(tag);
        if (className) {
            node.className = className;
        }
        if (value !== undefined) {
            node.textContent = text(value);
        }

        return node;
    }

    function actionButton(id, action, label) {
        const button = el('button', 'kds-action rounded-lg bg-slate-950 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-700', label);
        button.type = 'button';
        button.dataset.id = String(id);
        button.dataset.action = action;

        return button;
    }

    function renderTicket(ticket) {
        const sentAt = ticket.sent_at || ticket.updated_at || new Date().toISOString();
        const elapsed = diffMinutes(sentAt);
        const article = el('article', 'rounded-2xl border border-slate-700 bg-slate-800 p-4 shadow-lg');
        article.dataset.ticketId = String(ticket.id);
        article.dataset.station = text(ticket.station);

        const header = el('div', 'mb-3 flex items-start justify-between gap-3');
        const info = el('div');
        info.appendChild(el('div', 'text-3xl font-black', '#' + text(ticket.number)));
        info.appendChild(el('div', 'text-lg text-slate-300', ticket.order.table || ticket.order.label || config.translations.free));
        info.appendChild(el('div', 'text-sm text-slate-400', ticket.order.type || ''));
        header.appendChild(info);

        const statusWrap = el('div', 'space-y-2 text-end');
        const elapsedBadge = el('div', 'rounded-full px-3 py-1 text-sm font-bold', elapsed + 'm');
        elapsedBadge.classList.add(...elapsedClass(elapsed));
        statusWrap.appendChild(elapsedBadge);
        if (ticket.priority === 'rush') {
            statusWrap.appendChild(el('div', 'rounded-full bg-red-600 px-3 py-1 text-sm font-bold text-white', config.translations.rushBadge));
        }
        header.appendChild(statusWrap);
        article.appendChild(header);

        if (ticket.order.delivery && ticket.order.type === 'delivery') {
            const delivery = el('div', 'mb-3 rounded-xl bg-slate-900 p-3 text-sm text-slate-200');
            [ticket.order.delivery.name, ticket.order.delivery.phone, ticket.order.delivery.address].forEach((value) => {
                if (value) {
                    delivery.appendChild(el('div', '', value));
                }
            });
            article.appendChild(delivery);
        }

        const itemsWrap = el('div', 'space-y-2');
        (ticket.items || []).forEach((item) => {
            const itemCard = el('div', 'rounded-xl bg-slate-950/60 p-3');
            itemCard.appendChild(el('div', 'text-2xl font-black', `${text(item.quantity)} × ${text(item.name)}`));
            if (item.note) {
                itemCard.appendChild(el('div', 'mt-1 text-base text-amber-300', item.note));
            }
            itemsWrap.appendChild(itemCard);
        });
        article.appendChild(itemsWrap);

        const actions = el('div', 'mt-4 flex flex-wrap gap-2');
        if (ticket.status === 'new') actions.appendChild(actionButton(ticket.id, 'start', config.translations.start));
        if (ticket.status === 'new' || ticket.status === 'preparing') actions.appendChild(actionButton(ticket.id, 'ready', config.translations.ready));
        if (ticket.status === 'ready') actions.appendChild(actionButton(ticket.id, 'served', config.translations.served));
        if (ticket.status === 'ready' || ticket.status === 'served') actions.appendChild(actionButton(ticket.id, 'recall', config.translations.recall));
        actions.appendChild(actionButton(ticket.id, 'rush', config.translations.rush));
        if (ticket.status !== 'served') actions.appendChild(actionButton(ticket.id, 'cancel', config.translations.cancel));
        article.appendChild(actions);

        return article;
    }

    function renderColumns() {
        Object.values(columns).forEach((column) => {
            column.replaceChildren();
        });

        const stations = new Set();
        const readyIds = [];

        Array.from(state.ticketMap.values())
            .sort((left, right) => {
                const priorityLeft = left.priority === 'rush' ? 0 : 1;
                const priorityRight = right.priority === 'rush' ? 0 : 1;
                if (priorityLeft !== priorityRight) return priorityLeft - priorityRight;
                const order = { new: 0, preparing: 1, ready: 2, served: 3 };
                if ((order[left.status] ?? 9) !== (order[right.status] ?? 9)) return (order[left.status] ?? 9) - (order[right.status] ?? 9);
                return (left.number || 0) - (right.number || 0);
            })
            .forEach((ticket) => {
                if (ticket.station) {
                    stations.add(ticket.station);
                }
                if (stationFilter.value && ticket.station !== stationFilter.value) {
                    return;
                }
                const column = columns[ticket.status] || columns.served;
                column.appendChild(renderTicket(ticket));
                if (ticket.status === 'ready') {
                    readyIds.push(ticket.id);
                }
            });

        const currentStation = stationFilter.value;
        stationFilter.replaceChildren(new Option(config.translations.allStations, ''));
        Array.from(stations).sort().forEach((station) => stationFilter.appendChild(new Option(station, station)));
        stationFilter.value = currentStation;

        return readyIds;
    }

    function applyFeed(feed) {
        (feed.removed_ids || []).forEach((id) => state.ticketMap.delete(Number(id)));

        if (!feed.delta) {
            state.ticketMap.clear();
        }

        const readyBefore = new Set(Array.from(state.ticketMap.values()).filter((ticket) => ticket.status === 'ready').map((ticket) => ticket.id));

        (feed.tickets || []).forEach((ticket) => {
            state.ticketMap.set(Number(ticket.id), ticket);
        });

        const readyAfter = renderColumns();
        const newReady = readyAfter.some((id) => !readyBefore.has(id));
        if (newReady) {
            beep();
        }

        state.cursor = feed.cursor || state.cursor;
        state.etag = feed.etag || state.etag;
    }

    async function fetchFeed() {
        if (state.inFlight) {
            return;
        }

        state.inFlight = true;
        const headers = {};
        if (state.etag) {
            headers['If-None-Match'] = `"${state.etag}"`;
        }

        try {
            const query = state.cursor ? `?since=${encodeURIComponent(state.cursor)}` : '';
            const response = await fetch(config.feedUrl + query, {
                headers,
                credentials: 'same-origin',
            });

            if (response.status === 304) {
                connectionBanner.classList.add('hidden');
                authBanner.classList.add('hidden');
                state.delay = document.hidden ? Math.max(state.baseDelay, 15000) : state.baseDelay;
                return;
            }

            if (response.status === 401) {
                authBanner.textContent = config.translations.loginRequired;
                authBanner.classList.remove('hidden');
                state.delay = 30000;
                return;
            }

            if (!response.ok) {
                throw new Error('feed');
            }

            const feed = await response.json();
            connectionBanner.classList.add('hidden');
            authBanner.classList.add('hidden');
            applyFeed(feed);
            state.delay = document.hidden ? Math.max(state.baseDelay, 15000) : state.baseDelay;
        } catch (error) {
            connectionBanner.classList.remove('hidden');
            state.delay = Math.min(state.delay * 2, 30000);
        } finally {
            state.inFlight = false;
            scheduleNext();
        }
    }

    async function transition(id, action) {
        const payload = { action };
        if (action === 'cancel') {
            const reason = window.prompt(config.translations.cancelPrompt) || '';
            if (!reason) {
                return;
            }
            payload.reason = reason;
        }

        const card = root.querySelector(`[data-ticket-id="${CSS.escape(String(id))}"]`);
        if (card) {
            card.classList.add('opacity-60');
        }

        try {
            const response = await fetch(config.transitionUrl.replace('__ID__', String(id)), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify(payload),
            });

            if (response.status === 401) {
                authBanner.textContent = config.translations.loginRequired;
                authBanner.classList.remove('hidden');
                return;
            }

            if (!response.ok) {
                throw new Error(config.translations.updateFailed);
            }

            await fetchFeed();
        } catch (error) {
            connectionBanner.textContent = config.translations.updateFailed;
            connectionBanner.classList.remove('hidden');
            await fetchFeed();
        } finally {
            if (card) {
                card.classList.remove('opacity-60');
            }
        }
    }

    function scheduleNext() {
        clearTimeout(state.timerId);
        state.timerId = setTimeout(fetchFeed, state.delay);
    }

    root.addEventListener('click', (event) => {
        if (!state.unlocked) {
            unlockSound();
        }

        const button = event.target.closest('.kds-action');
        if (button) {
            transition(button.dataset.id, button.dataset.action);
        }
    });

    stationFilter.addEventListener('change', renderColumns);
    soundToggle.addEventListener('click', () => {
        if (!state.unlocked) {
            unlockSound();
            return;
        }
        state.muted = !state.muted;
        soundToggle.textContent = state.muted ? config.translations.unmute : config.translations.mute;
    });
    themeToggle.addEventListener('click', () => {
        document.body.classList.toggle('bg-white');
        document.body.classList.toggle('text-slate-950');
        document.body.classList.toggle('bg-slate-950');
        document.body.classList.toggle('text-white');
    });
    fullscreenToggle.addEventListener('click', async () => {
        if (!document.fullscreenElement) {
            await document.documentElement.requestFullscreen();
        } else {
            await document.exitFullscreen();
        }
    });
    document.addEventListener('visibilitychange', () => {
        state.delay = document.hidden ? Math.max(state.baseDelay, 15000) : state.baseDelay;
        scheduleNext();
    });

    fetchFeed();
})();
