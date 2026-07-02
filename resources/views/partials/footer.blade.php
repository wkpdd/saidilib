@php use App\Models\Setting; $locale = app()->getLocale(); @endphp
<footer class="mt-16 bg-ink-900 text-slate-300">
    <div class="container-x grid gap-8 py-12 sm:grid-cols-2 lg:grid-cols-4">
        <div>
            <div class="flex items-center gap-2">
                <span class="grid h-10 w-10 place-items-center rounded-xl bg-brand-600 text-white text-xl">✏️</span>
                <span class="font-display text-lg font-bold text-white">{{ Setting::get('store_name', 'Saidi Papetrie') }}</span>
            </div>
            <p class="mt-3 text-sm leading-relaxed text-slate-400">
                {{ $locale === 'ar' ? Setting::get('tagline_ar') : Setting::get('tagline_fr') }}
            </p>
            <div class="mt-4 flex gap-2">
                @if (Setting::get('facebook'))<a href="{{ Setting::get('facebook') }}" class="grid h-9 w-9 place-items-center rounded-lg bg-white/10 hover:bg-white/20">f</a>@endif
                @if (Setting::get('instagram'))<a href="{{ Setting::get('instagram') }}" class="grid h-9 w-9 place-items-center rounded-lg bg-white/10 hover:bg-white/20">◎</a>@endif
                @if (Setting::get('tiktok'))<a href="{{ Setting::get('tiktok') }}" class="grid h-9 w-9 place-items-center rounded-lg bg-white/10 hover:bg-white/20">♪</a>@endif
            </div>
        </div>

        <div>
            <h4 class="font-semibold text-white">{{ __('shop.shop') }}</h4>
            <ul class="mt-3 space-y-2 text-sm">
                <li><a href="{{ route('catalog') }}" class="hover:text-white">{{ __('shop.all_products') }}</a></li>
                <li><a href="{{ route('about') }}" class="hover:text-white">{{ __('shop.about') }}</a></li>
                <li><a href="{{ route('contact') }}" class="hover:text-white">{{ __('shop.contact') }}</a></li>
            </ul>
        </div>

        <div>
            <h4 class="font-semibold text-white">{{ __('shop.contact') }}</h4>
            <ul class="mt-3 space-y-2 text-sm text-slate-400">
                <li>📞 {{ Setting::get('phone') }}</li>
                <li>✉️ {{ Setting::get('email') }}</li>
                <li>📍 {{ $locale === 'ar' ? Setting::get('address_ar') : Setting::get('address_fr') }}</li>
                <li>🕐 {{ Setting::get('hours') }}</li>
            </ul>
        </div>

        <div>
            <h4 class="font-semibold text-white">{{ __('shop.delivery') }}</h4>
            <p class="mt-3 text-sm text-slate-400">{{ __('shop.cod') }} — 58 wilayas.</p>
            <div class="mt-3 flex flex-wrap gap-2 text-xs">
                <span class="badge bg-white/10 text-white">Noest</span>
                <span class="badge bg-white/10 text-white">Yalidine</span>
                <span class="badge bg-white/10 text-white">{{ __('shop.cod') }}</span>
            </div>
        </div>
    </div>
    <div class="border-t border-white/10">
        <div class="container-x flex flex-col items-center justify-between gap-2 py-4 text-xs text-slate-500 sm:flex-row">
            <span>© {{ date('Y') }} {{ Setting::get('store_name', 'Saidi Papetrie') }}. Tous droits réservés.</span>
            <span>Paiement à la livraison · Livraison 58 wilayas</span>
        </div>
    </div>
</footer>
