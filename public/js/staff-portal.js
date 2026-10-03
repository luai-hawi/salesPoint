(function () {
    var boot = window.StaffPortalBoot || {};
    var state = boot.state || {};
    var routes = boot.routes || {};
    var locale = boot.locale || 'en';
    var queuePrefix = 'staff-queue:' + boot.portalKey;
    var legacyQueueKey = queuePrefix;
    var latestGeo = null;
    var timerId = null;
    var installPrompt = null;

    var nodes = {
        loginSection: document.getElementById('login-section'),
        portalSection: document.getElementById('portal-section'),
        portalDisabled: document.getElementById('portal-disabled'),
        portalUnavailable: document.getElementById('portal-unavailable'),
        secureWarning: document.getElementById('secure-warning'),
        offlineWarning: document.getElementById('offline-warning'),
        flash: document.getElementById('flash-message'),
        loginForm: document.getElementById('login-form'),
        loginSubmit: document.getElementById('login-submit'),
        employeeName: document.getElementById('employee-name'),
        employeeTitle: document.getElementById('employee-title'),
        statusBadge: document.getElementById('status-badge'),
        statusCaption: document.getElementById('status-caption'),
        liveTimer: document.getElementById('live-timer'),
        primaryPunch: document.getElementById('primary-punch'),
        biometricPunch: document.getElementById('biometric-punch'),
        setupBiometric: document.getElementById('setup-biometric'),
        locationBadge: document.getElementById('location-badge'),
        locationSummary: document.getElementById('location-summary'),
        locationExtra: document.getElementById('location-extra'),
        monthHoursCard: document.getElementById('month-hours-card'),
        monthHours: document.getElementById('month-hours'),
        monthPay: document.getElementById('month-pay'),
        monthPayCard: document.getElementById('month-pay-card'),
        recentDays: document.getElementById('recent-days'),
        recentEmpty: document.getElementById('recent-empty'),
        logoutButton: document.getElementById('logout-button'),
    };

    function t(key) {
        return (boot.strings || {})[key] || key;
    }

    function showFlash(message, tone) {
        if (!nodes.flash) return;
        nodes.flash.textContent = message;
        nodes.flash.className = 'alert';
        nodes.flash.classList.add(tone === 'danger' ? 'alert-danger' : tone === 'warning' ? 'alert-warning' : 'alert-info');
        nodes.flash.classList.remove('hidden');
        window.clearTimeout(nodes.flashTimer);
        nodes.flashTimer = window.setTimeout(function () {
            nodes.flash.classList.add('hidden');
        }, 5000);
    }

    function getCsrf() {
        return state.csrf || '';
    }

    function request(url, options) {
        options = options || {};
        options.headers = options.headers || {};
        options.headers['Accept'] = 'application/json';
        options.credentials = 'same-origin';
        if (options.body && !options.headers['Content-Type']) options.headers['Content-Type'] = 'application/json';
        if (options.method && options.method !== 'GET') {
            options.headers['X-Staff-Token'] = getCsrf();
        }
        return fetch(url, options).then(function (response) {
            return response.json().catch(function () {
                return {};
            }).then(function (data) {
                if (!response.ok) {
                    throw data;
                }
                return data;
            });
        });
    }

    function renderState(next) {
        state = next || {};
        document.documentElement.dir = boot.dir || (locale === 'ar' ? 'rtl' : 'ltr');
        nodes.secureWarning.classList.toggle('hidden', !!state.secure_context);
        nodes.offlineWarning.classList.toggle('hidden', navigator.onLine);
        nodes.portalDisabled.classList.toggle('hidden', !!state.attendance_enabled);
        if (nodes.portalUnavailable) {
            nodes.portalUnavailable.textContent = state.unavailable_message || '';
            nodes.portalUnavailable.classList.toggle('hidden', !state.unavailable_message);
        }

        if (!state.attendance_enabled || state.portal_available === false) {
            nodes.loginSection.classList.add('hidden');
            nodes.portalSection.classList.add('hidden');
            return;
        }

        if (!state.authenticated) {
            nodes.loginSection.classList.remove('hidden');
            nodes.portalSection.classList.add('hidden');
            return;
        }

        nodes.loginSection.classList.add('hidden');
        nodes.portalSection.classList.remove('hidden');
        nodes.employeeName.textContent = state.employee ? state.employee.name : '';
        nodes.employeeTitle.textContent = state.employee && state.employee.job_title ? state.employee.job_title : '';
        nodes.statusBadge.textContent = state.attendance ? (state.attendance.status_label || '') : '';
        nodes.statusCaption.textContent = statusCaption(state);
        nodes.primaryPunch.textContent = state.attendance && state.attendance.button ? state.attendance.button.label : '';
        nodes.monthHours.textContent = state.stats && state.stats.month_hours ? state.stats.month_hours : '—';
        nodes.monthPay.textContent = state.stats && state.stats.month_pay ? state.stats.month_pay : '—';
        nodes.monthHoursCard.classList.toggle('hidden', !(state.settings && state.settings.show_hours_to_staff));
        nodes.monthPayCard.classList.toggle('hidden', !(state.settings && state.settings.show_pay_to_staff));
        nodes.setupBiometric.classList.toggle('hidden', !(state.settings && state.settings.show_biometric_setup));
        nodes.biometricPunch.classList.toggle('hidden', !(window.StaffWebAuthn && window.StaffWebAuthn.supported()));
        renderRecentDays();
        updateTimer();
        renderLocation();
    }

    function statusCaption(snapshot) {
        if (!snapshot.attendance) return '';
        var today = snapshot.attendance.today;
        if (!today) return snapshot.attendance.status === 'open' ? '' : '';
        if (snapshot.attendance.status === 'open') {
            return (today.check_in_time || '') + ' • ' + (state.settings && state.settings.allow_remote_checkout ? '' : '');
        }
        if (today.check_out_time) {
            return (today.check_in_time || '—') + ' → ' + today.check_out_time;
        }
        return today.check_in_time || '';
    }

    function renderRecentDays() {
        var days = state.recent_days || [];
        nodes.recentDays.innerHTML = '';
        nodes.recentEmpty.classList.toggle('hidden', days.length > 0);
        days.forEach(function (day) {
            var article = document.createElement('article');
            article.className = 'day';
            var badgeClass = day.flagged ? 'badge-warning' : (day.status === 'closed' ? 'badge-success' : 'badge-info');
            article.innerHTML =
                '<div class="day-top"><strong>' + escapeHtml(day.day_label || day.work_date || '') + '</strong>' +
                '<span class="badge ' + badgeClass + '">' + escapeHtml(day.status_label || day.status || '') + '</span></div>' +
                '<div class="day-time">' + escapeHtml((day.check_in_time || '—') + ' → ' + (day.check_out_time || '—')) + '</div>' +
                '<div class="split">' + (day.hours ? '<span class="day-time">' + escapeHtml(day.hours) + '</span>' : '') +
                (day.remote ? '<span class="badge badge-warning">' + escapeHtml(day.remote_label || '') + '</span>' : '') + '</div>';
            nodes.recentDays.appendChild(article);
        });
    }

    function renderLocation() {
        if (!latestGeo) {
            nodes.locationBadge.textContent = t('locationPending');
            nodes.locationBadge.className = 'badge badge-warning';
            nodes.locationSummary.textContent = t('locationPending');
            nodes.locationExtra.textContent = '';
            return;
        }

        var badge = latestGeo.inside ? ['badge badge-success', t('inside')] : ['badge badge-danger', t('outside')];
        nodes.locationBadge.className = badge[0];
        nodes.locationBadge.textContent = badge[1];
        var parts = [];
        if (latestGeo.location_name) parts.push(latestGeo.location_name);
        if (typeof latestGeo.distance_m === 'number') parts.push(latestGeo.distance_m + ' m');
        nodes.locationSummary.textContent = parts.join(' • ') || t('locationPending');
        nodes.locationExtra.textContent = latestGeo.accuracy ? ('±' + Math.round(latestGeo.accuracy) + ' m') : '';
    }

    function updateTimer() {
        if (timerId) window.clearInterval(timerId);
        var since = state.attendance && state.attendance.live_since ? new Date(state.attendance.live_since) : null;
        if (!since || state.attendance.status !== 'open') {
            nodes.liveTimer.classList.add('hidden');
            return;
        }

        nodes.liveTimer.classList.remove('hidden');
        var tick = function () {
            var diff = Math.max(0, Math.floor((Date.now() - since.getTime()) / 1000));
            var hours = String(Math.floor(diff / 3600)).padStart(2, '0');
            var minutes = String(Math.floor((diff % 3600) / 60)).padStart(2, '0');
            var seconds = String(diff % 60).padStart(2, '0');
            nodes.liveTimer.textContent = hours + ':' + minutes + ':' + seconds;
        };
        tick();
        timerId = window.setInterval(tick, 1000);
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function acquireLocation() {
        if (!navigator.geolocation) {
            showFlash(t('locationUnsupported'), 'warning');
            return Promise.resolve(null);
        }

        if (!window.isSecureContext && !/^(localhost|127\.0\.0\.1)$/.test(window.location.hostname)) {
            showFlash(t('secureContextRequired'), 'warning');
            return Promise.resolve(null);
        }

        return new Promise(function (resolve) {
            navigator.geolocation.getCurrentPosition(function (position) {
                resolve({
                    lat: position.coords.latitude,
                    lng: position.coords.longitude,
                    accuracy: position.coords.accuracy,
                });
            }, function (error) {
                if (error.code === 1) showFlash(t('locationDenied'), 'warning');
                else if (error.code === 3) showFlash(t('locationTimeout'), 'warning');
                else showFlash(t('locationUnavailable'), 'warning');
                resolve(null);
            }, { enableHighAccuracy: true, timeout: 12000, maximumAge: 15000 });
        });
    }

    function refreshState(withGeo) {
        var url = new URL(routes.state, window.location.origin);
        var geoPromise = withGeo ? acquireLocation() : Promise.resolve(null);
        return geoPromise.then(function (geo) {
            if (geo) {
                url.searchParams.set('lat', geo.lat);
                url.searchParams.set('lng', geo.lng);
                url.searchParams.set('accuracy', geo.accuracy);
            }
            return request(url.toString(), { method: 'GET' }).then(function (data) {
                latestGeo = data.geofence || geo;
                renderState(data);
                return data;
            });
        }).catch(function () {
            renderState(state);
        });
    }

    function currentQueueIdentity(snapshot) {
        if (!(snapshot && snapshot.authenticated && snapshot.employee && snapshot.device)) return null;
        var employeeId = snapshot.employee.id;
        var deviceId = snapshot.device.id;
        if (employeeId == null || deviceId == null) return null;

        return {
            employee_id: String(employeeId),
            device_id: String(deviceId)
        };
    }

    function queueKeyForIdentity(identity) {
        if (!(identity && identity.employee_id && identity.device_id)) return legacyQueueKey;
        return queuePrefix + ':' + identity.employee_id + ':' + identity.device_id;
    }

    function entryMatchesIdentity(entry, identity) {
        if (!(entry && identity)) return false;
        if (!entry.queue_identity) return false;

        return String(entry.queue_identity.employee_id) === String(identity.employee_id)
            && String(entry.queue_identity.device_id) === String(identity.device_id);
    }

    function readStoredQueue(key) {
        try {
            var parsed = JSON.parse(window.localStorage.getItem(key) || '[]');
            return Array.isArray(parsed) ? parsed : [];
        } catch (error) {
            return [];
        }
    }

    function writeStoredQueue(key, queue) {
        window.localStorage.setItem(key, JSON.stringify(queue));
    }

    function clearPortalQueues() {
        if (!(window.localStorage && typeof window.localStorage.length === 'number')) return;

        for (var index = window.localStorage.length - 1; index >= 0; index -= 1) {
            var key = window.localStorage.key(index);
            if (key && key.indexOf(queuePrefix) === 0) {
                window.localStorage.removeItem(key);
            }
        }
    }

    function loadQueue(snapshot) {
        var identity = currentQueueIdentity(snapshot);
        if (!identity) return [];

        window.localStorage.removeItem(legacyQueueKey);
        var key = queueKeyForIdentity(identity);
        var queue = readStoredQueue(key);
        var filtered = queue.filter(function (entry) {
            return entryMatchesIdentity(entry, identity);
        });

        if (filtered.length !== queue.length) {
            writeStoredQueue(key, filtered);
        }

        return filtered;
    }

    function queueOfflinePunch(payload) {
        var identity = currentQueueIdentity(state);
        if (!identity) return;

        payload.queue_identity = identity;
        var key = queueKeyForIdentity(identity);
        var queue = loadQueue(state);
        queue.push(payload);
        writeStoredQueue(key, queue);
        showFlash(t('queuedOffline'), 'info');
    }

    function flushQueue() {
        if (!navigator.onLine) return Promise.resolve();
        var identity = currentQueueIdentity(state);
        var key = queueKeyForIdentity(identity);
        var queue = loadQueue(state);
        if (!queue.length || !state.authenticated) return Promise.resolve();

        var next = queue.shift();
        if (!next) return Promise.resolve();
        if (!entryMatchesIdentity(next, identity)) {
            writeStoredQueue(key, queue);
            return flushQueue();
        }
        nodes.primaryPunch.disabled = true;
        return request(routes.punch, {
            method: 'POST',
            body: JSON.stringify(next),
        }).then(function (data) {
            writeStoredQueue(key, queue);
            latestGeo = data.state ? data.state.geofence : latestGeo;
            renderState(data.state || state);
            return flushQueue();
        }).catch(function () {
            queue.unshift(next);
            writeStoredQueue(key, queue);
        }).finally(function () {
            nodes.primaryPunch.disabled = false;
        });
    }

    function beginBiometricRegistration() {
        if (!(window.StaffWebAuthn && window.StaffWebAuthn.supported())) {
            showFlash(t('biometricUnsupported'), 'warning');
            return;
        }

        request(routes.registerOptions, { method: 'POST' }).then(function (options) {
            return window.StaffWebAuthn.createCredential(options).then(function (credential) {
                return request(routes.registerVerify, {
                    method: 'POST',
                    body: JSON.stringify({
                        challenge_id: options.challenge_id,
                        credential: credential,
                        label: navigator.userAgent,
                    }),
                });
            });
        }).then(function (data) {
            showFlash(data.message, 'info');
            return refreshState(false);
        }).catch(function (error) {
            if (error && error.message) showFlash(error.message, 'warning');
        });
    }

    function maybeBiometricAssertion() {
        if (!(window.StaffWebAuthn && window.StaffWebAuthn.supported())) return Promise.resolve(null);
        var required = state.employee && state.employee.biometric_required;
        if (!required) return Promise.resolve(null);

        return request(routes.authOptions, { method: 'POST' }).then(function (options) {
            return window.StaffWebAuthn.getAssertion(options).then(function (credential) {
                return {
                    challenge_id: options.challenge_id,
                    credential: credential,
                };
            });
        });
    }

    function punch(useBiometricButton) {
        if (!state.authenticated) return;
        nodes.primaryPunch.disabled = true;
        nodes.biometricPunch.disabled = true;

        Promise.all([
            acquireLocation(),
            useBiometricButton ? request(routes.authOptions, { method: 'POST' }).then(function (options) {
                return window.StaffWebAuthn.getAssertion(options).then(function (credential) {
                    return { challenge_id: options.challenge_id, credential: credential };
                });
            }).catch(function () { return null; }) : maybeBiometricAssertion(),
        ]).then(function (results) {
            var geo = results[0];
            var assertion = results[1];
            var payload = {
                action: state.attendance && state.attendance.button ? state.attendance.button.action : 'check_in',
                request_id: newRequestId(),
                lat: geo ? geo.lat : null,
                lng: geo ? geo.lng : null,
                accuracy: geo ? geo.accuracy : null,
            };
            if (assertion) {
                payload.challenge_id = assertion.challenge_id;
                payload.credential = assertion.credential;
            }
            if (!navigator.onLine) {
                payload.offline = true;
                payload.client_time = new Date().toISOString();
                if (state.attendance && state.attendance.button && state.attendance.button.action === 'check_out') {
                    payload.claimed_time = payload.client_time;
                    payload.reason = t('offlineCheckoutReason');
                }
                queueOfflinePunch(payload);
                return refreshState(false);
            }
            return request(routes.punch, {
                method: 'POST',
                body: JSON.stringify(payload),
            }).then(function (data) {
                latestGeo = geo;
                renderState(data.state || state);
                showFlash(data.message, 'info');
            });
        }).catch(function (error) {
            if (error && error.message) showFlash(error.message, 'warning');
        }).finally(function () {
            nodes.primaryPunch.disabled = false;
            nodes.biometricPunch.disabled = false;
        });
    }

    function init() {
        if (!navigator.onLine) nodes.offlineWarning.classList.remove('hidden');
        window.addEventListener('online', function () {
            nodes.offlineWarning.classList.add('hidden');
            refreshState(true);
            flushQueue();
        });
        window.addEventListener('offline', function () {
            nodes.offlineWarning.classList.remove('hidden');
        });
        window.addEventListener('beforeinstallprompt', function (event) {
            installPrompt = event;
        });

        nodes.loginForm.addEventListener('submit', function (event) {
            event.preventDefault();
            nodes.loginSubmit.disabled = true;
            request(routes.login, {
                method: 'POST',
                body: JSON.stringify({
                    username: document.getElementById('username').value,
                    password: document.getElementById('password').value,
                    login_nonce: state.login_nonce || '',
                }),
            }).then(function (data) {
                latestGeo = data.state ? data.state.geofence : null;
                renderState(data.state || state);
                showFlash(data.message, 'info');
                window.localStorage.removeItem(legacyQueueKey);
                flushQueue();
            }).catch(function (error) {
                showFlash(error.message || 'Error', 'warning');
            }).finally(function () {
                nodes.loginSubmit.disabled = false;
            });
        });

        nodes.primaryPunch.addEventListener('click', function () { punch(false); });
        nodes.biometricPunch.addEventListener('click', function () { punch(true); });
        nodes.setupBiometric.addEventListener('click', beginBiometricRegistration);
        nodes.logoutButton.addEventListener('click', function () {
            request(routes.logout, { method: 'POST' }).then(function (data) {
                clearPortalQueues();
                showFlash(data.message, 'info');
                refreshState(false);
            });
        });

        renderState(state);
        refreshState(true);
        flushQueue();
    }

    window.StaffPortalTest = {
        currentQueueIdentity: currentQueueIdentity,
        queueKeyForIdentity: queueKeyForIdentity,
        entryMatchesIdentity: entryMatchesIdentity,
        clearPortalQueues: clearPortalQueues,
        loadQueue: loadQueue,
        queuePrefix: queuePrefix,
    };

    if (!window.__STAFF_PORTAL_DISABLE_AUTO_INIT__) {
        init();
    }

    function newRequestId() {
        if (window.crypto && typeof window.crypto.randomUUID === 'function') {
            return window.crypto.randomUUID();
        }

        return 'req-' + Date.now() + '-' + Math.random().toString(36).slice(2, 12);
    }
})();
