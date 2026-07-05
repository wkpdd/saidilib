@php
    use App\Models\Setting;
    use App\Models\Category;
    $cart = app(\App\Services\CartService::class);
    $navCategories = \Illuminate\Support\Facades\Schema::hasTable('categories')
        ? Category::active()->whereNull('parent_id')->orderBy('sort_order')->take(8)->get()
        : collect();
    $locale = app()->getLocale();
@endphp

{{-- Announcement bar --}}
<div class="bg-ink-900 text-white">
    <div class="container-x py-2 text-center text-xs sm:text-sm font-medium">
        {{ $locale === 'ar' ? Setting::get('announcement_ar') : Setting::get('announcement_fr') }}
    </div>
</div>

<header class="sticky top-0 z-40 bg-white/95 backdrop-blur border-b border-slate-100">
    <div class="container-x">
        <div class="flex items-center gap-4 py-3">
            {{-- Logo --}}
            <a href="{{ route('home') }}" class="flex items-center gap-2 shrink-0">
                <img src="{{ asset('logov2.jpeg') }}" alt="{{ Setting::get('store_name', 'Saidi Papetrie') }}"
                     class="h-12 w-auto sm:h-14" width="240" height="138">
            </a>

            {{-- Search --}}
            <form action="{{ route('catalog') }}" method="get" class="hidden flex-1 md:block">
                <div class="relative">
                    <input type="text" name="q" value="{{ request('q') }}"
                        placeholder="{{ __('shop.search') }}"
                        class="input ps-11">
                    <svg class="pointer-events-none absolute inset-y-0 start-3 my-auto h-5 w-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
                </div>
            </form>

            {{-- Actions --}}
            <div class="ms-auto flex items-center gap-1.5">
                {{-- Language switch --}}
                <div class="flex rounded-xl bg-slate-100 p-0.5 text-sm font-semibold">
                    <a href="{{ route('locale.switch', 'fr') }}" class="rounded-lg px-2.5 py-1 {{ $locale==='fr' ? 'bg-white shadow-soft text-brand-700' : 'text-slate-500' }}">FR</a>
                    <a href="{{ route('locale.switch', 'ar') }}" class="rounded-lg px-2.5 py-1 {{ $locale==='ar' ? 'bg-white shadow-soft text-brand-700' : 'text-slate-500' }}">ع</a>
                </div>

                {{-- Account --}}
                @auth('client')
                    <a href="{{ route('account.index') }}" title="{{ __('shop.account') }}"
                       class="grid h-10 w-10 place-items-center rounded-xl bg-brand-50 text-brand-700 hover:bg-brand-100">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    </a>
                @else
                    <a href="{{ route('account.login') }}" title="{{ __('shop.login') }}"
                       class="grid h-10 w-10 place-items-center rounded-xl hover:bg-slate-100">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    </a>
                @endauth

                {{-- Cart --}}
                <a href="{{ route('cart.index') }}" class="relative grid h-10 w-10 place-items-center rounded-xl hover:bg-slate-100">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    <span class="absolute -top-1 -end-1 grid h-5 min-w-5 place-items-center rounded-full bg-accent px-1 text-[11px] font-bold text-white">{{ $cart->count() }}</span>
                </a>

                {{-- Mobile menu toggle --}}
                <button data-toggle="#mobileNav" class="grid h-10 w-10 place-items-center rounded-xl hover:bg-slate-100 md:hidden">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
            </div>
        </div>

        {{-- Category nav (desktop) --}}
        <nav class="hidden items-center gap-1 pb-2 md:flex">
            <a href="{{ route('catalog') }}" class="rounded-lg px-3 py-1.5 text-sm font-semibold text-ink-700 hover:bg-brand-50 hover:text-brand-700">{{ __('shop.all_products') }}</a>
            @foreach ($navCategories as $cat)
                <a href="{{ route('category', $cat->slug) }}" class="rounded-lg px-3 py-1.5 text-sm font-medium text-ink-700 hover:bg-brand-50 hover:text-brand-700">
                    <span>{{ $cat->icon }}</span> {{ $cat->name }}
                </a>
            @endforeach
        </nav>
    </div>

    {{-- Mobile nav --}}
    <div id="mobileNav" class="hidden border-t border-slate-100 bg-white md:hidden">
        <div class="container-x py-3 space-y-1">
            <form action="{{ route('catalog') }}" method="get" class="mb-2">
                <input type="text" name="q" placeholder="{{ __('shop.search') }}" class="input">
            </form>
            <a href="{{ route('catalog') }}" class="block rounded-lg px-3 py-2 text-sm font-semibold hover:bg-slate-50">{{ __('shop.all_products') }}</a>
            @foreach ($navCategories as $cat)
                <a href="{{ route('category', $cat->slug) }}" class="block rounded-lg px-3 py-2 text-sm hover:bg-slate-50">{{ $cat->icon }} {{ $cat->name }}</a>
            @endforeach
        </div>
    </div>
</header>
