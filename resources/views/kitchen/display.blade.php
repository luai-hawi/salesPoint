<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('restaurant.titles.kitchen') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-950 text-white">
    @php
        $kdsConfig = [
            'feedUrl' => route('kitchen.feed'),
            'transitionUrl' => route('kitchen.tickets.transition', ['ticket' => '__ID__']),
            'thresholds' => $thresholds,
            'pollSeconds' => $pollSeconds,
            'locale' => app()->getLocale(),
            'translations' => [
                'newTitle' => __('restaurant.kds.new_title'),
                'preparingTitle' => __('restaurant.kds.preparing_title'),
                'readyTitle' => __('restaurant.kds.ready_title'),
                'servedTitle' => __('restaurant.kds.served_title'),
                'connectionLost' => __('restaurant.labels.connection_lost'),
                'soundLocked' => __('restaurant.labels.sound_locked'),
                'free' => __('restaurant.labels.no_table'),
                'start' => __('restaurant.buttons.start'),
                'ready' => __('restaurant.buttons.ready'),
                'served' => __('restaurant.buttons.served'),
                'recall' => __('restaurant.buttons.recall'),
                'rush' => __('restaurant.buttons.rush'),
                'cancel' => __('restaurant.buttons.cancel'),
                'fullscreen' => __('restaurant.buttons.fullscreen'),
                'mute' => __('restaurant.buttons.mute'),
                'unmute' => __('restaurant.buttons.unmute'),
                'allStations' => __('restaurant.labels.all_stations'),
                'rushBadge' => __('restaurant.labels.rush_badge'),
                'cancelPrompt' => __('restaurant.messages.cancel_prompt'),
                'updateFailed' => __('restaurant.messages.kds_update_failed'),
                'loginRequired' => __('restaurant.messages.kds_login_required'),
            ],
        ];
    @endphp
    <div id="restaurant-kds"
        data-config='@json($kdsConfig)'>
        <header class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-800 px-4 py-4">
            <div>
                <h1 class="text-3xl font-bold">{{ __('restaurant.titles.kitchen') }}</h1>
                <p class="text-sm text-slate-300">{{ __('restaurant.subtitles.kitchen') }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <select id="kds-station-filter" class="rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm">
                    <option value="">{{ __('restaurant.labels.all_stations') }}</option>
                </select>
                <button id="kds-sound-toggle" class="rounded-lg border border-slate-700 px-4 py-2 text-sm font-semibold text-white">
                    {{ __('restaurant.buttons.unmute') }}
                </button>
                <button id="kds-theme-toggle" class="rounded-lg border border-slate-700 px-4 py-2 text-sm font-semibold text-white">
                    {{ __('restaurant.buttons.light') }}
                </button>
                <button id="kds-fullscreen-toggle" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                    {{ __('restaurant.buttons.fullscreen') }}
                </button>
            </div>
        </header>

        <div id="kds-connection-banner" class="hidden bg-amber-500 px-4 py-2 text-sm font-semibold text-slate-950">
            {{ __('restaurant.labels.connection_lost') }}
        </div>
        <div id="kds-auth-banner" class="hidden bg-red-500 px-4 py-2 text-sm font-semibold text-white">
            {{ __('restaurant.labels.login_required') }}
        </div>

        <div id="kds-sound-banner" class="bg-sky-500 px-4 py-2 text-sm font-semibold text-slate-950">
            {{ __('restaurant.labels.sound_locked') }}
        </div>

        <main class="grid gap-4 px-4 py-4 lg:grid-cols-4">
            <section class="rounded-2xl bg-slate-900 p-4 shadow-xl">
                <h2 class="mb-4 text-2xl font-bold">{{ __('restaurant.kds.new_title') }}</h2>
                <div id="kds-column-new" class="space-y-3"></div>
            </section>
            <section class="rounded-2xl bg-slate-900 p-4 shadow-xl">
                <h2 class="mb-4 text-2xl font-bold">{{ __('restaurant.kds.preparing_title') }}</h2>
                <div id="kds-column-preparing" class="space-y-3"></div>
            </section>
            <section class="rounded-2xl bg-slate-900 p-4 shadow-xl">
                <h2 class="mb-4 text-2xl font-bold">{{ __('restaurant.kds.ready_title') }}</h2>
                <div id="kds-column-ready" class="space-y-3"></div>
            </section>
            <section class="rounded-2xl bg-slate-900 p-4 shadow-xl">
                <h2 class="mb-4 text-2xl font-bold">{{ __('restaurant.kds.served_title') }}</h2>
                <div id="kds-column-served" class="space-y-3"></div>
            </section>
        </main>
    </div>

    <script src="{{ \App\Support\Assets::versioned('js/restaurant-kds.js') }}" defer></script>
</body>
</html>
