@php
    $locale = app()->getLocale();
    $dir = $locale === 'ar' ? 'rtl' : 'ltr';
    use App\Models\Setting;
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $dir }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>@yield('title', Setting::get('store_name', 'Saidi Papetrie'))</title>
    <meta name="description" content="@yield('meta_description', Setting::get('meta_description'))">

    {{-- PWA / Android installable app --}}
    <link rel="manifest" href="/manifest.webmanifest">
    <meta name="theme-color" content="#e07d00">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Saidi">
    <link rel="apple-touch-icon" href="/img/apple-touch.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/img/favicon-32.png">
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E%E2%9C%8F%EF%B8%8F%3C/text%3E%3C/svg%3E">
    {{-- Fonts: preconnect early, load async with display=swap so text renders immediately on slow links.
         Slimmed to the weights actually used (Cairo body 400/600/700, Poppins headings 600/700). --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" media="print" onload="this.media='all'"
          href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&family=Poppins:wght@600;700&display=swap">
    <noscript>
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&family=Poppins:wght@600;700&display=swap">
    </noscript>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
    {{-- Tracking pixels (global + page-specific) --}}
    @include('partials.pixels', ['pixels' => $pagePixels ?? app(\App\Services\PixelService::class)->forPage($product ?? null)])
</head>
<body>
    @include('partials.header')

    <main class="min-h-[60vh]">
        @if (session('success'))
            <div class="container-x mt-4">
                <div class="rounded-xl bg-green-50 px-4 py-3 text-sm font-medium text-green-800 ring-1 ring-green-200">
                    {{ session('success') }}
                </div>
            </div>
        @endif
        @if (session('error'))
            <div class="container-x mt-4">
                <div class="rounded-xl bg-red-50 px-4 py-3 text-sm font-medium text-red-800 ring-1 ring-red-200">
                    {{ session('error') }}
                </div>
            </div>
        @endif
        @yield('content')
    </main>

    @include('partials.footer')

    @stack('scripts')
    @include('partials.pixels-noscript', ['pixels' => $pagePixels ?? collect()])

    {{-- Register the service worker (offline + connection resilience) --}}
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
        }
    </script>
</body>
</html>
