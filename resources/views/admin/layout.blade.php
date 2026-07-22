@php use App\Models\Setting; @endphp
<!DOCTYPE html>
<html lang="fr" dir="ltr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Admin') — {{ Setting::get('store_name', 'Saidi') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-100">
<div class="flex min-h-screen">
    {{-- Sidebar --}}
    <aside id="adminNav" class="fixed inset-y-0 z-40 hidden w-64 flex-col bg-ink-900 text-slate-300 lg:flex">
        <div class="px-4 py-4">
            <a href="{{ route('admin.dashboard') }}" class="block rounded-xl bg-white p-2">
                <img src="{{ asset('logov2.jpeg') }}" alt="{{ Setting::get('store_name', 'Saidi') }}" class="mx-auto h-12 w-auto">
            </a>
        </div>
        <nav class="flex-1 space-y-1 px-3 py-2 text-sm">
            @php
                $u = auth()->user();
                $nav = [
                    ['admin.dashboard', '📊 Tableau de bord', 'dashboard', true],
                    ['admin.orders.index', '🧾 Commandes', 'orders', $u->hasPermission('orders')],
                    ['admin.clients.index', '💳 Clients', 'clients', $u->hasPermission('clients')],
                    ['admin.products.index', '📦 Produits', 'products', $u->hasPermission('products')],
                    ['admin.categories.index', '🗂️ Catégories', 'categories', $u->hasPermission('categories')],
                    ['admin.suppliers.index', '📥 Fournisseurs', 'suppliers', $u->hasPermission('purchasing')],
                    ['admin.receipts.index', '📦 Réceptions stock', 'receipts', $u->hasPermission('purchasing')],
                    ['admin.stock.index', '🏬 Stock multi-emplacements', 'stock', $u->hasPermission('purchasing')],
                    ['admin.social.index', '📣 Réseaux sociaux', 'social', $u->hasPermission('social')],
                    ['admin.incidents.index', '🧯 Pertes & casses', 'incidents', $u->hasPermission('incidents')],
                    ['admin.pixels.index', '🎯 Pixels', 'pixels', $u->hasPermission('pixels')],
                    ['admin.wilayas.index', '🚚 Livraison', 'wilayas', $u->hasPermission('wilayas')],
                    ['admin.users.index', '👥 Équipe', 'users', $u->hasPermission('users')],
                    ['admin.settings.edit', '⚙️ Paramètres', 'settings', $u->hasPermission('settings')],
                ];
            @endphp
            @foreach ($nav as [$route, $label, $key, $show])
                @continue(! $show)
                <a href="{{ route($route) }}"
                   class="flex items-center gap-2 rounded-xl px-4 py-2.5 transition {{ request()->routeIs('admin.'.$key.'*') ? 'bg-white/10 font-semibold text-white' : 'hover:bg-white/5' }}">
                    {{ $label }}
                </a>
            @endforeach
        </nav>
        <div class="border-t border-white/10 p-3">
            <a href="{{ route('home') }}" target="_blank" class="block rounded-xl px-4 py-2 text-sm hover:bg-white/5">🌐 Voir la boutique</a>
            <form action="{{ route('admin.logout') }}" method="post">
                @csrf
                <button class="block w-full rounded-xl px-4 py-2 text-start text-sm text-red-300 hover:bg-white/5">↩ Déconnexion</button>
            </form>
        </div>
    </aside>

    {{-- Main --}}
    <div class="flex-1 lg:ms-64">
        <header class="sticky top-0 z-30 flex items-center gap-3 border-b border-slate-200 bg-white px-4 py-3 lg:px-8">
            <button data-toggle="#adminNav" class="lg:hidden">☰</button>
            <h1 class="font-display text-lg font-bold">@yield('heading', '')</h1>
            <div class="ms-auto flex items-center gap-3 text-sm">
                {{-- Notifications bell --}}
                @php $unread = \App\Models\AdminNotification::unread()->count(); @endphp
                <a href="{{ route('admin.notifications.index') }}" class="relative grid h-9 w-9 place-items-center rounded-xl hover:bg-slate-100" title="Notifications">
                    <svg class="h-6 w-6 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6 6 0 10-12 0v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                    @if ($unread)
                        <span class="absolute -top-0.5 -end-0.5 grid h-5 min-w-5 place-items-center rounded-full bg-red-500 px-1 text-[11px] font-bold text-white">{{ $unread > 9 ? '9+' : $unread }}</span>
                    @endif
                </a>
                <span class="text-slate-500">{{ auth()->user()->name }}</span>
                <span class="grid h-8 w-8 place-items-center rounded-full bg-brand-100 text-brand-700">{{ substr(auth()->user()->name, 0, 1) }}</span>
            </div>
        </header>

        <main class="p-4 lg:p-8">
            @if (session('success'))
                <div class="mb-4 rounded-xl bg-green-50 px-4 py-3 text-sm font-medium text-green-800 ring-1 ring-green-200">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="mb-4 rounded-xl bg-red-50 px-4 py-3 text-sm font-medium text-red-800 ring-1 ring-red-200">{{ session('error') }}</div>
            @endif
            @if ($errors->any())
                <div class="mb-4 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-800 ring-1 ring-red-200">
                    <ul class="list-disc ps-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                </div>
            @endif
            @yield('content')
        </main>
    </div>
</div>
@stack('scripts')
</body>
</html>
