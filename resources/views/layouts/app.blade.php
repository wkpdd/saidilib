@php
    $locale = app()->getLocale();
    $dir = $locale === 'ar' ? 'rtl' : 'ltr';
    use App\Models\Setting;
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $dir }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', Setting::get('store_name', 'Saidi Papetrie'))</title>
    <meta name="description" content="@yield('meta_description', Setting::get('meta_description'))">
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E%E2%9C%8F%EF%B8%8F%3C/text%3E%3C/svg%3E">
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
        @yield('content')
    </main>

    @include('partials.footer')

    @stack('scripts')
    @include('partials.pixels-noscript', ['pixels' => $pagePixels ?? collect()])
</body>
</html>
