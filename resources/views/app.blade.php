<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark bg-slate-950">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">

        <title inertia>{{ config('app.name', 'XAU Trading Journal') }}</title>

        <!-- PWA Meta Tags & Manifest -->
        <link rel="manifest" href="/site.webmanifest">
        <link rel="icon" type="image/svg+xml" href="/icons/icon.svg">
        <link rel="apple-touch-icon" href="/icons/icon.svg">
        <meta name="theme-color" content="#020617">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="XAU Journal">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased bg-slate-950 text-slate-100 min-h-screen">
        @inertia

        <script>
            if ('serviceWorker' in navigator) {
                window.addEventListener('load', () => {
                    navigator.serviceWorker.register('/sw.js').catch((err) => {
                        console.log('SW registration skipped:', err);
                    });
                });
            }
        </script>
    </body>
</html>
