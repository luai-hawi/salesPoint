<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <title>{{ __('staff.ui.portal_title', ['shop' => $owner->name]) }}</title>
    @include('staff.partials.head', ['owner' => $owner])
</head>
<body>
<div class="shell" id="staff-app-root">
    <div class="topbar">
        <div class="brand">
            <img src="{{ asset('images/logo.png') }}" alt="">
            <div>
                <h1>{{ __('staff.ui.portal_title', ['shop' => $owner->name]) }}</h1>
                <p>{{ __('staff.ui.portal_subtitle') }}</p>
            </div>
        </div>
        <div class="locale-switch">
            <a href="{{ route('staff.lang', ['key' => $owner->staff_portal_key, 'locale' => 'ar']) }}">AR</a>
            <a href="{{ route('staff.lang', ['key' => $owner->staff_portal_key, 'locale' => 'en']) }}">EN</a>
        </div>
    </div>

    <div id="secure-warning" class="alert alert-warning hidden">{{ __('staff.location.secure_context_required') }}</div>
    <div id="portal-unavailable" class="alert alert-danger hidden"></div>
    <div id="offline-warning" class="alert alert-info hidden">{{ __('staff.ui.no_connection') }}</div>
    <div id="portal-disabled" class="alert alert-danger hidden">{{ __('staff.errors.attendance_disabled') }}</div>
    <div id="flash-message" class="alert alert-info hidden"></div>

    <section id="login-section" class="card hidden">
        <div class="stack">
            <div>
                <h2 style="margin:0 0 .35rem;">{{ __('staff.ui.sign_in_heading') }}</h2>
                <p class="muted" style="margin:0;">{{ __('staff.ui.sign_in_help') }}</p>
            </div>

            <form id="login-form" class="stack">
                <div class="field">
                    <label for="username">{{ __('staff.form.username') }}</label>
                    <input id="username" name="username" autocomplete="username" required>
                </div>
                <div class="field">
                    <label for="password">{{ __('staff.form.password') }}</label>
                    <input id="password" name="password" type="password" autocomplete="current-password" required>
                </div>
                <button id="login-submit" type="submit" class="btn btn-primary btn-block">{{ __('staff.actions.sign_in') }}</button>
            </form>

            <ul class="hint-list small">
                <li>{{ __('staff.ui.location_hint') }}</li>
                <li>{{ __('staff.ui.biometric_hint') }}</li>
                <li>{{ __('staff.ui.home_screen_hint') }}</li>
            </ul>
        </div>
    </section>

    <section id="portal-section" class="stack hidden">
        <div class="card">
            <div class="split">
                <div>
                    <div class="muted small">{{ __('staff.ui.welcome') }}</div>
                    <h2 id="employee-name" style="margin:.25rem 0 0;"></h2>
                    <p id="employee-title" class="muted" style="margin:.35rem 0 0;"></p>
                </div>
                <span id="status-badge" class="badge badge-info">{{ __('staff.ui.loading') }}</span>
            </div>
            <div id="status-caption" class="muted small" style="margin-top:.75rem;"></div>
            <div id="live-timer" class="timer hidden">00:00:00</div>
            <div class="actions" style="margin-top:1rem;">
                <button id="primary-punch" class="btn btn-primary btn-big btn-block">{{ __('staff.actions.check_in') }}</button>
                <button id="biometric-punch" class="btn btn-secondary btn-block hidden">{{ __('staff.actions.biometric_punch') }}</button>
                <button id="setup-biometric" class="btn btn-secondary btn-block hidden">{{ __('staff.actions.setup_biometric') }}</button>
            </div>
        </div>

        <div class="card">
            <div class="split">
                <h3 style="margin:0;">{{ __('staff.ui.location_card') }}</h3>
                <span id="location-badge" class="badge badge-warning">{{ __('staff.location.pending') }}</span>
            </div>
            <p id="location-summary" class="muted" style="margin:.75rem 0 .35rem;"></p>
            <p id="location-extra" class="small muted" style="margin:0;"></p>
        </div>

        <div class="grid-2">
            <div class="stat" id="month-hours-card">
                <span class="label">{{ __('staff.ui.month_hours') }}</span>
                <div id="month-hours" class="value">00:00</div>
            </div>
            <div class="stat" id="month-pay-card">
                <span class="label">{{ __('staff.ui.month_pay') }}</span>
                <div id="month-pay" class="value">—</div>
            </div>
        </div>

        <div class="card">
            <div class="split" style="margin-bottom:.75rem;">
                <h3 style="margin:0;">{{ __('staff.ui.recent_days') }}</h3>
                <a class="btn btn-secondary" href="{{ route('staff.history', $owner->staff_portal_key) }}">{{ __('staff.actions.full_history') }}</a>
            </div>
            <div id="recent-days" class="days"></div>
            <div id="recent-empty" class="empty hidden">{{ __('staff.ui.no_history') }}</div>
        </div>

        <div class="card">
            <div class="actions">
                <button id="logout-button" class="btn btn-danger">{{ __('staff.actions.logout') }}</button>
                <a class="btn btn-secondary" href="{{ route('staff.history', $owner->staff_portal_key) }}">{{ __('staff.actions.full_history') }}</a>
            </div>
        </div>
    </section>
</div>

<script>
    window.StaffPortalBoot = {{ \Illuminate\Support\Js::from($boot) }};
</script>
<script src="{{ \App\Support\Assets::versioned('js/staff-webauthn.js') }}"></script>
<script src="{{ \App\Support\Assets::versioned('js/staff-portal.js') }}"></script>
</body>
</html>
