<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta http-equiv="Feature-Policy" content="usb *; bluetooth *">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Favicon -->
    <link rel="icon" href="{{ asset('images/logo4.png') }}" type="image/png">

    <!-- PWA Manifest -->
    <link rel="manifest" href="{{ asset('pwa/manifest.json') }}">
    <meta name="theme-color" content="#3b82f6">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="SalesPoint">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    @php
        // FORCE locale setting - this is a temporary fix to test
        $sessionLocale = session('locale', 'en');
        if (in_array($sessionLocale, ['en', 'ar'])) {
            app()->setLocale($sessionLocale);
        }
    @endphp

    <!-- Comprehensive RTL CSS - Optimized Version -->
    @if (app()->getLocale() === 'ar')
        <style>
            /* =========================
           CORE RTL SETUP
           ========================= */
            body {
                direction: rtl;
            }

            /* =========================
           ESSENTIAL MARGIN/PADDING FIXES
           ========================= */

            /* Use logical properties for better RTL support */
            [dir="rtl"] .ml-1 {
                margin-left: 0;
                margin-right: 0.25rem;
            }

            [dir="rtl"] .ml-2 {
                margin-left: 0;
                margin-right: 0.5rem;
            }

            [dir="rtl"] .ml-3 {
                margin-left: 0;
                margin-right: 0.75rem;
            }

            [dir="rtl"] .ml-4 {
                margin-left: 0;
                margin-right: 1rem;
            }

            [dir="rtl"] .ml-5 {
                margin-left: 0;
                margin-right: 1.25rem;
            }

            [dir="rtl"] .ml-6 {
                margin-left: 0;
                margin-right: 1.5rem;
            }

            [dir="rtl"] .ml-8 {
                margin-left: 0;
                margin-right: 2rem;
            }

            [dir="rtl"] .ml-auto {
                margin-left: 0;
                margin-right: auto;
            }

            [dir="rtl"] .mr-1 {
                margin-right: 0;
                margin-left: 0.25rem;
            }

            [dir="rtl"] .mr-2 {
                margin-right: 0;
                margin-left: 0.5rem;
            }

            [dir="rtl"] .mr-3 {
                margin-right: 0;
                margin-left: 0.75rem;
            }

            [dir="rtl"] .mr-4 {
                margin-right: 0;
                margin-left: 1rem;
            }

            [dir="rtl"] .mr-5 {
                margin-right: 0;
                margin-left: 1.25rem;
            }

            [dir="rtl"] .mr-6 {
                margin-right: 0;
                margin-left: 1.5rem;
            }

            [dir="rtl"] .mr-8 {
                margin-right: 0;
                margin-left: 2rem;
            }

            [dir="rtl"] .mr-auto {
                margin-right: 0;
                margin-left: auto;
            }

            [dir="rtl"] .pl-1 {
                padding-left: 0;
                padding-right: 0.25rem;
            }

            [dir="rtl"] .pl-2 {
                padding-left: 0;
                padding-right: 0.5rem;
            }

            [dir="rtl"] .pl-3 {
                padding-left: 0;
                padding-right: 0.75rem;
            }

            [dir="rtl"] .pl-4 {
                padding-left: 0;
                padding-right: 1rem;
            }

            [dir="rtl"] .pl-5 {
                padding-left: 0;
                padding-right: 1.25rem;
            }

            [dir="rtl"] .pl-6 {
                padding-left: 0;
                padding-right: 1.5rem;
            }

            [dir="rtl"] .pl-8 {
                padding-left: 0;
                padding-right: 2rem;
            }

            [dir="rtl"] .pl-10 {
                padding-left: 0;
                padding-right: 2.5rem;
            }

            [dir="rtl"] .pl-12 {
                padding-left: 0;
                padding-right: 3rem;
            }

            [dir="rtl"] .pr-1 {
                padding-right: 0;
                padding-left: 0.25rem;
            }

            [dir="rtl"] .pr-2 {
                padding-right: 0;
                padding-left: 0.5rem;
            }

            [dir="rtl"] .pr-3 {
                padding-right: 0;
                padding-left: 0.75rem;
            }

            [dir="rtl"] .pr-4 {
                padding-right: 0;
                padding-left: 1rem;
            }

            [dir="rtl"] .pr-5 {
                padding-right: 0;
                padding-left: 1.25rem;
            }

            [dir="rtl"] .pr-6 {
                padding-right: 0;
                padding-left: 1.5rem;
            }

            [dir="rtl"] .pr-8 {
                padding-right: 0;
                padding-left: 2rem;
            }

            [dir="rtl"] .pr-10 {
                padding-right: 0;
                padding-left: 2.5rem;
            }

            [dir="rtl"] .pr-12 {
                padding-right: 0;
                padding-left: 3rem;
            }

            /* =========================
           POSITIONING UTILITIES
           ========================= */
            [dir="rtl"] .left-0 {
                left: auto;
                right: 0;
            }

            [dir="rtl"] .left-1 {
                left: auto;
                right: 0.25rem;
            }

            [dir="rtl"] .left-2 {
                left: auto;
                right: 0.5rem;
            }

            [dir="rtl"] .left-3 {
                left: auto;
                right: 0.75rem;
            }

            [dir="rtl"] .left-4 {
                left: auto;
                right: 1rem;
            }

            [dir="rtl"] .left-5 {
                left: auto;
                right: 1.25rem;
            }

            [dir="rtl"] .left-6 {
                left: auto;
                right: 1.5rem;
            }

            [dir="rtl"] .right-0 {
                right: auto;
                left: 0;
            }

            [dir="rtl"] .right-1 {
                right: auto;
                left: 0.25rem;
            }

            [dir="rtl"] .right-2 {
                right: auto;
                left: 0.5rem;
            }

            [dir="rtl"] .right-3 {
                right: auto;
                left: 0.75rem;
            }

            [dir="rtl"] .right-4 {
                right: auto;
                left: 1rem;
            }

            [dir="rtl"] .right-5 {
                right: auto;
                left: 1.25rem;
            }

            [dir="rtl"] .right-6 {
                right: auto;
                left: 1.5rem;
            }

            /* =========================
           FLEXBOX UTILITIES
           ========================= */
            [dir="rtl"] .space-x-1> :not([hidden])~ :not([hidden]) {
                --tw-space-x-reverse: 1;
            }

            [dir="rtl"] .space-x-2> :not([hidden])~ :not([hidden]) {
                --tw-space-x-reverse: 1;
            }

            [dir="rtl"] .space-x-3> :not([hidden])~ :not([hidden]) {
                --tw-space-x-reverse: 1;
            }

            [dir="rtl"] .space-x-4> :not([hidden])~ :not([hidden]) {
                --tw-space-x-reverse: 1;
            }

            /* =========================
           TEXT ALIGNMENT
           ========================= */
            [dir="rtl"] .text-left {
                text-align: right;
            }

            [dir="rtl"] .text-right {
                text-align: left;
            }

            /* =========================
           FORM ELEMENTS
           ========================= */
            [dir="rtl"] input:not([type="checkbox"]):not([type="radio"]):not([type="submit"]):not([type="button"]),
            [dir="rtl"] textarea,
            [dir="rtl"] select {
                text-align: right;
            }

            /* Placeholder text alignment */
            [dir="rtl"] input::placeholder,
            [dir="rtl"] textarea::placeholder {
                text-align: right;
            }

            /* =========================
           INPUT PADDING FIXES FOR RTL
           ========================= */

            /* Specific fix for inputs with icons */
            [dir="rtl"] input.pl-10.pr-3 {
                padding-left: 0.75rem !important;
                padding-right: 2.5rem !important;
            }

            [dir="rtl"] input.pl-8 {
                padding-left: 0.75rem !important;
                padding-right: 2rem !important;
            }

            [dir="rtl"] input.pl-12 {
                padding-left: 0.75rem !important;
                padding-right: 3rem !important;
            }

            /* Ensure icons are positioned correctly */
            [dir="rtl"] .absolute.left-3 {
                left: auto !important;
                right: 0.75rem !important;
            }

            [dir="rtl"] .absolute.left-4 {
                left: auto !important;
                right: 1rem !important;
            }

            /* =========================
           BORDER RADIUS - KEEP ORIGINAL STYLING
           ========================= */
            [dir="rtl"] .rounded-l-lg {
                border-top-left-radius: 0;
                border-bottom-left-radius: 0;
                border-top-right-radius: 0.5rem;
                border-bottom-right-radius: 0.5rem;
            }

            [dir="rtl"] .rounded-r-lg {
                border-top-right-radius: 0;
                border-bottom-right-radius: 0;
                border-top-left-radius: 0.5rem;
                border-bottom-left-radius: 0.5rem;
            }

            /* =========================
           ICONS AND DIRECTIONAL ELEMENTS
           ========================= */
            [dir="rtl"] .rtl-flip,
            [dir="rtl"] [class*="arrow"],
            [dir="rtl"] [class*="chevron"] {
                transform: scaleX(-1);
            }

            /* Icon spacing in flex containers */
            [dir="rtl"] svg+span,
            [dir="rtl"] svg+div,
            [dir="rtl"] .w-4+*,
            [dir="rtl"] .w-5+*,
            [dir="rtl"] .w-8+* {
                margin-right: 0.5rem;
                margin-left: 0.5rem !important;
            }

            /* Specific icon spacing classes */
            [dir="rtl"] .icon-spacing {
                margin-left: 0.75rem !important;
                margin-right: 0;
            }

            /* =========================
           RESPONSIVE UTILITIES (Essential ones only)
           ========================= */
            @media (min-width: 640px) {
                [dir="rtl"] .sm\:ml-1 {
                    margin-left: 0;
                    margin-right: 0.25rem;
                }

                [dir="rtl"] .sm\:ml-2 {
                    margin-left: 0;
                    margin-right: 0.5rem;
                }

                [dir="rtl"] .sm\:ml-3 {
                    margin-left: 0;
                    margin-right: 0.75rem;
                }

                [dir="rtl"] .sm\:ml-4 {
                    margin-left: 0;
                    margin-right: 1rem;
                }

                [dir="rtl"] .sm\:ml-6 {
                    margin-left: 0;
                    margin-right: 1.5rem;
                }

                [dir="rtl"] .sm\:mr-1 {
                    margin-right: 0;
                    margin-left: 0.25rem;
                }

                [dir="rtl"] .sm\:mr-2 {
                    margin-right: 0;
                    margin-left: 0.5rem;
                }

                [dir="rtl"] .sm\:mr-3 {
                    margin-right: 0;
                    margin-left: 0.75rem;
                }

                [dir="rtl"] .sm\:mr-4 {
                    margin-right: 0;
                    margin-left: 1rem;
                }

                [dir="rtl"] .sm\:mr-6 {
                    margin-right: 0;
                    margin-left: 1.5rem;
                }

                [dir="rtl"] .sm\:pl-3 {
                    padding-left: 0;
                    padding-right: 0.75rem;
                }

                [dir="rtl"] .sm\:pl-4 {
                    padding-left: 0;
                    padding-right: 1rem;
                }

                [dir="rtl"] .sm\:pl-6 {
                    padding-left: 0;
                    padding-right: 1.5rem;
                }

                [dir="rtl"] .sm\:pr-3 {
                    padding-right: 0;
                    padding-left: 0.75rem;
                }

                [dir="rtl"] .sm\:pr-4 {
                    padding-right: 0;
                    padding-left: 1rem;
                }

                [dir="rtl"] .sm\:pr-6 {
                    padding-right: 0;
                    padding-left: 1.5rem;
                }
            }

            @media (min-width: 768px) {
                [dir="rtl"] .md\:ml-1 {
                    margin-left: 0;
                    margin-right: 0.25rem;
                }

                [dir="rtl"] .md\:ml-2 {
                    margin-left: 0;
                    margin-right: 0.5rem;
                }

                [dir="rtl"] .md\:ml-3 {
                    margin-left: 0;
                    margin-right: 0.75rem;
                }

                [dir="rtl"] .md\:ml-4 {
                    margin-left: 0;
                    margin-right: 1rem;
                }

                [dir="rtl"] .md\:ml-6 {
                    margin-left: 0;
                    margin-right: 1.5rem;
                }

                [dir="rtl"] .md\:mr-1 {
                    margin-right: 0;
                    margin-left: 0.25rem;
                }

                [dir="rtl"] .md\:mr-2 {
                    margin-right: 0;
                    margin-left: 0.5rem;
                }

                [dir="rtl"] .md\:mr-3 {
                    margin-right: 0;
                    margin-left: 0.75rem;
                }

                [dir="rtl"] .md\:mr-4 {
                    margin-right: 0;
                    margin-left: 1rem;
                }

                [dir="rtl"] .md\:mr-6 {
                    margin-right: 0;
                    margin-left: 1.5rem;
                }
            }

            @media (min-width: 1024px) {
                [dir="rtl"] .lg\:ml-1 {
                    margin-left: 0;
                    margin-right: 0.25rem;
                }

                [dir="rtl"] .lg\:ml-2 {
                    margin-left: 0;
                    margin-right: 0.5rem;
                }

                [dir="rtl"] .lg\:ml-3 {
                    margin-left: 0;
                    margin-right: 0.75rem;
                }

                [dir="rtl"] .lg\:ml-4 {
                    margin-left: 0;
                    margin-right: 1rem;
                }

                [dir="rtl"] .lg\:ml-6 {
                    margin-left: 0;
                    margin-right: 1.5rem;
                }

                [dir="rtl"] .lg\:mr-1 {
                    margin-right: 0;
                    margin-left: 0.25rem;
                }

                [dir="rtl"] .lg\:mr-2 {
                    margin-right: 0;
                    margin-left: 0.5rem;
                }

                [dir="rtl"] .lg\:mr-3 {
                    margin-right: 0;
                    margin-left: 0.75rem;
                }

                [dir="rtl"] .lg\:mr-4 {
                    margin-right: 0;
                    margin-left: 1rem;
                }

                [dir="rtl"] .lg\:mr-6 {
                    margin-right: 0;
                    margin-left: 1.5rem;
                }
            }

            /* =========================
           UTILITY CLASSES FOR SPECIAL CASES
           ========================= */

            /* Force RTL for critical elements */
            [dir="rtl"] .force-rtl {
                direction: rtl !important;
                text-align: right !important;
            }

            /* Force LTR for specific elements that should remain LTR */
            [dir="rtl"] .force-ltr {
                direction: ltr !important;
                text-align: left !important;
            }

            /* Force specific margin/padding when auto-conversion doesn't work */
            [dir="rtl"] .force-mr-4 {
                margin-left: 1rem !important;
                margin-right: 0 !important;
            }

            [dir="rtl"] .force-ml-4 {
                margin-right: 1rem !important;
                margin-left: 0 !important;
            }

            [dir="rtl"] .force-pr-4 {
                padding-left: 1rem !important;
                padding-right: 0 !important;
            }

            [dir="rtl"] .force-pl-4 {
                padding-right: 1rem !important;
                padding-left: 0 !important;
            }
        </style>
    @endif

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="font-sans antialiased">
    <div class="min-h-screen bg-gray-100">
        @include('layouts.navigation')

        <!-- Page Heading -->
        @isset($header)
            <header class="bg-white shadow">
                <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                    {{ $header }}
                </div>
            </header>
        @endisset

        <!-- Page Content -->
        <main>
            {{ $slot }}
        </main>
    </div>

    <!-- HTML5 QR Code Scanner Library -->
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>

    <!-- Add this line to include pushed scripts -->
    @stack('scripts')

    <!-- PWA Registration Script -->
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register('/pwa/sw.js')
                    .then(function(registration) {
                        console.log('PWA Service Worker registered with scope:', registration.scope);
                    })
                    .catch(function(error) {
                        console.log('PWA Service Worker registration failed:', error);
                    });
            });
        }

        // PWA Install Prompt
        let deferredPrompt;
        let installButton = null;

        window.addEventListener('beforeinstallprompt', function(e) {
            console.log('Before install prompt triggered');
            e.preventDefault();
            deferredPrompt = e;

            // Show install button if it exists
            installButton = document.getElementById('pwa-install-btn');
            if (installButton) {
                installButton.style.display = 'flex';
            }
        });

        window.addEventListener('appinstalled', function(e) {
            console.log('PWA was installed');
            deferredPrompt = null;
            if (installButton) {
                installButton.style.display = 'none';
            }
        });

        async function installPWA() {
            if (deferredPrompt) {
                deferredPrompt.prompt();
                const {
                    outcome
                } = await deferredPrompt.userChoice;
                console.log('Install outcome:', outcome);
                deferredPrompt = null;
            }
        }
    </script>
</body>

</html>
