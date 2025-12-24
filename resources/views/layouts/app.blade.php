<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="{{ __('translations.home.hero_description') }}">
    @if(config('app.env') === 'staging')
    <meta name="robots" content="noindex, nofollow">
    @endif
    <title>@yield('title', config('site.name') . ' - ' . __('translations.home.hero_title'))</title>

    <!-- Favicons -->
    <link rel="icon" type="image/png" href="{{ asset('favicon-96x96.png') }}" sizes="96x96">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
    <meta name="apple-mobile-web-app-title" content="{{ config('site.name') }}">

    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">

    <!-- Vite Assets -->
    @vite(['resources/css/main.css', 'resources/js/app.js'])

    <!-- Custom Styles -->
    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1e40af;
            --secondary: #2563eb;
            --accent: #8B5CF6;
            --light: #F9FAFB;
            --dark: #1F2937;
            --success: #10B981;
            --warning: #F59E0B;
            --danger: #EF4444;
        }

    </style>

    <!-- Extra Styles -->
    @stack('styles')
</head>
<body data-page="@yield('page', 'default')" class="antialiased text-gray-800 bg-gray-50 flex flex-col min-h-screen">
<div id="app" class="min-h-screen flex flex-col">
    @if(config('app.env') === 'staging')
    <!-- Staging Environment Warning Banner -->
    <div style="background: linear-gradient(90deg, #f97316, #dc2626); color: white; border-bottom: 4px solid #b91c1c; box-shadow: 0 4px 6px rgba(0,0,0,0.1); position: sticky; top: 0; z-index: 9999;">
        <div style="max-width: 1200px; margin: 0 auto; padding: 1rem; text-align: center;">
            <div style="display: flex; align-items: center; justify-content: center; gap: 0.75rem; margin-bottom: 0.5rem;">
                <i class="fas fa-exclamation-triangle" style="font-size: 1.5rem; animation: pulse 2s infinite;"></i>
                <span style="font-size: 1.25rem; font-weight: 900; text-transform: uppercase; letter-spacing: 0.05em;">
                    Staging Environment
                </span>
                <i class="fas fa-exclamation-triangle" style="font-size: 1.5rem; animation: pulse 2s infinite;"></i>
            </div>
            <p style="font-size: 0.95rem; font-weight: 600; margin: 0.25rem 0;">
                ⚠️ This is the staging/pre-production version ⚠️
            </p>
            <p style="font-size: 0.85rem; font-weight: 500; margin: 0.25rem 0;">
                Data is periodically deleted. Do not use real information.
            </p>
            @if(config('app.production_url'))
            <p style="font-size: 0.85rem; font-weight: 700; margin: 0.25rem 0;">
                Production: <a href="{{ config('app.production_url') }}" style="text-decoration: underline; color: #fef08a;" target="_blank">{{ parse_url(config('app.production_url'), PHP_URL_HOST) }}</a>
            </p>
            @endif
        </div>
    </div>
    @endif

    @include('partials.header')

    <main class="flex-grow">
        @yield('content')
    </main>

    @include('partials.footer')
</div>

@stack('scripts')
</body>
</html>
