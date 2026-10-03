<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <title>{{ __('staff.ui.history_title') }}</title>
    @include('staff.partials.head', ['owner' => $owner])
</head>
<body>
<div class="shell">
    <div class="topbar">
        <div class="brand">
            <img src="{{ asset('images/logo.png') }}" alt="">
            <div>
                <h1>{{ __('staff.ui.history_title') }}</h1>
                <p>{{ $employee->name }}</p>
            </div>
        </div>
        <a class="btn btn-secondary" href="{{ $portalUrl }}">{{ __('staff.actions.back_to_portal') }}</a>
    </div>

    <div class="card">
        <div class="days">
            @forelse ($records as $record)
                <article class="day">
                    <div class="day-top">
                        <strong>{{ $record['work_date'] }}</strong>
                        @php
                            $tone = $record['status'] === 'closed' ? 'badge-success' : ($record['status'] === 'needs_review' ? 'badge-warning' : 'badge-info');
                        @endphp
                        <span class="badge {{ $tone }}">{{ __('staff.status.' . $record['status']) }}</span>
                    </div>
                    <div class="day-time">{{ __('staff.ui.check_in_label') }}: {{ $record['check_in'] ?? '—' }}</div>
                    <div class="day-time">{{ __('staff.ui.check_out_label') }}: {{ $record['check_out'] ?? '—' }}</div>
                    @if ($record['hours'] !== null)
                        <div class="day-time">{{ __('staff.ui.hours_label') }}: {{ $record['hours'] }}</div>
                    @endif
                    @if ($record['remote'])
                        <div class="day-time">{{ __('staff.ui.remote_checkout') }}</div>
                    @endif
                    @if ($record['reason'])
                        <div class="day-time">{{ $record['reason'] }}</div>
                    @endif
                </article>
            @empty
                <div class="empty">{{ __('staff.ui.no_history') }}</div>
            @endforelse
        </div>
    </div>

    <div class="card">
        {{ $records->links() }}
    </div>
</div>
</body>
</html>
