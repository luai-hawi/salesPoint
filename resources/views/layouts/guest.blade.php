<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Set locale directly in the view -->
        @php
            $locale = session('locale', request()->cookie('locale', config('app.locale')));
            if (in_array($locale, ['en', 'ar'])) {
                app()->setLocale($locale);
            }
        @endphp

        <!-- RTL quick fixes -->
        @if(app()->getLocale() === 'ar')
        <style>
            body { direction: rtl; text-align: right; }
        </style>
        @endif

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased" >
        <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-gray-100" style="padding-bottom: 200px;">
            <div class="w-full max-w-md flex items-center justify-between px-6">
                <a href="/">
                    <x-application-logo class="w-25 h-25 fill-current text-gray-500" />
                </a>
                <div>
                    <a href="{{ route('lang.switch', 'en') }}" class="px-2 py-1 text-sm rounded {{ app()->getLocale()=='en' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-700' }}">EN</a>
                    <a href="{{ route('lang.switch', 'ar') }}" class="px-2 py-1 text-sm rounded {{ app()->getLocale()=='ar' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-700' }}">AR</a>
                </div>
            </div>

            <div class="w-full sm:max-w-md mt-6 px-6 py-4 bg-white shadow-md overflow-hidden sm:rounded-lg" style="margin-top: -90px;">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
