<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'light') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "light" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: oklch(1 0 0);
            }

            html.dark {
                background-color: oklch(0.145 0 0);
            }
        </style>

        {{-- ?v=: bump when the logo files change, so Cloudflare/WhatsApp caches fetch the new image --}}
        <link rel="icon" href="/Logo.ico?v=3" sizes="any">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png?v=3">

        {{-- Link-preview (WhatsApp/Facebook/Telegram) metadata: these crawlers don't run JS, so it must be server-rendered --}}
        <meta name="description" content="GoldWave — Gold & Silver Jewellery Membership">
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="GoldWave">
        <meta property="og:title" content="GoldWave">
        <meta property="og:description" content="GoldWave — Gold & Silver Jewellery Membership">
        <meta property="og:url" content="{{ url()->full() }}">
        {{-- Large 1200x630 baseline JPEG (~80 KB): WhatsApp shows it as a big, sharp banner. Without an og:image it
             shrinks the apple-touch-icon into a tiny blurry thumbnail. Keep it a JPEG under 300 KB. --}}
        <meta property="og:image" content="{{ asset('Images/og-banner.jpg') }}?v=1">
        <meta property="og:image:type" content="image/jpeg">
        <meta property="og:image:width" content="1200">
        <meta property="og:image:height" content="630">
        <meta property="og:image:alt" content="GoldWave logo">
        <meta name="twitter:card" content="summary_large_image">

        @fonts

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        <x-inertia::head>
            <title>{{ config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
