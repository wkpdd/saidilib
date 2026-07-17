@extends('layouts.app')

@section('content')
{{-- Hero --}}
<section class="relative overflow-hidden bg-gradient-to-br from-brand-700 via-brand-600 to-brand-800 text-white">
    <div class="container-x grid items-center gap-8 py-14 lg:grid-cols-2 lg:py-20">
        <div class="animate-fade-up">
            <span class="badge bg-white/15 text-white">📦 {{ __('shop.cod') }} · 58 wilayas</span>
            <h1 class="mt-4 font-display text-4xl font-extrabold leading-tight sm:text-5xl">
                {{ app()->getLocale()==='ar' ? 'كل ما تحتاجه المدرسة والمكتب' : "Toute la papeterie, l'école et le bureau" }}
            </h1>
            <p class="mt-4 max-w-md text-brand-100">
                {{ app()->getLocale()==='ar'
                    ? 'تشكيلة واسعة من اللوازم المدرسية والمكتبية والمعلوماتية. الدفع عند الاستلام والتوصيل لكل الولايات.'
                    : "Fournitures scolaires, bureautiques et informatiques de qualité. Paiement à la livraison, partout en Algérie." }}
            </p>
            <div class="mt-6 flex flex-wrap gap-3">
                <a href="{{ route('catalog') }}" class="btn-accent">{{ __('shop.shop_now') }}</a>
                <a href="#categories" class="btn bg-white/10 text-white ring-1 ring-white/30 hover:bg-white/20">{{ __('shop.categories') }}</a>
            </div>
            <div class="mt-8 flex flex-wrap gap-6 text-sm text-brand-100">
                <div class="flex items-center gap-2">✅ {{ __('shop.cod') }}</div>
                <div class="flex items-center gap-2">🚚 Noest · Yalidine</div>
                <div class="flex items-center gap-2">↩️ Retour facile</div>
            </div>
        </div>
        <div class="relative hidden lg:block">
            <div class="grid grid-cols-2 gap-4">
                @foreach ($featured->take(4) as $p)
                    <div class="rounded-2xl bg-white/10 p-3 backdrop-blur ring-1 ring-white/20 {{ $loop->iteration % 2 ? 'translate-y-4' : '' }}">
                        <img src="{{ $p->card_image_url }}" width="300" height="300" loading="lazy" decoding="async" class="aspect-square w-full rounded-xl object-cover" alt="{{ $p->display_name }}">
                        <p class="mt-2 truncate text-sm font-medium">{{ $p->display_name }}</p>
                        <p class="text-sm font-bold text-accent">@money($p->price)</p>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</section>

{{-- Trust strip --}}
<section class="border-b border-slate-100 bg-white">
    <div class="container-x grid grid-cols-2 gap-4 py-6 text-center sm:grid-cols-4">
        @foreach ([['🚚','Livraison 58 wilayas'],['💵','Paiement à la livraison'],['✅','Produits garantis'],['📞','Support 7j/7']] as $f)
            <div class="flex flex-col items-center gap-1">
                <span class="text-2xl">{{ $f[0] }}</span>
                <span class="text-xs font-semibold text-ink-700">{{ $f[1] }}</span>
            </div>
        @endforeach
    </div>
</section>

{{-- Categories — the "industry of categories" feel --}}
<section id="categories" class="container-x py-12">
    <div class="mb-6 flex items-end justify-between">
        <div>
            <h2 class="font-display text-2xl font-bold">{{ __('shop.categories') }}</h2>
            <p class="text-sm text-slate-500">Explorez nos univers</p>
        </div>
        <a href="{{ route('catalog') }}" class="text-sm font-semibold text-brand-700 hover:underline">{{ __('shop.view_all') }} →</a>
    </div>
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
        @foreach ($categories as $cat)
            @if ($cat->slug === 'jeux-pour-enfants')
                {{-- Special kids card: spans two columns, rainbow shine, playful icon --}}
                <a href="{{ route('category', $cat->slug) }}"
                   class="group relative col-span-2 flex items-center gap-4 overflow-hidden rounded-2xl p-5 text-left text-white shadow-card ring-1 ring-white/30 transition hover:-translate-y-1"
                   style="background: linear-gradient(110deg, #ec4899, #f59e0b, #22d3ee, #a855f7, #ec4899); background-size: 200% 100%;"
                   >
                    <span class="pointer-events-none absolute inset-0 animate-shine" style="background: linear-gradient(110deg, #ec4899, #f59e0b, #22d3ee, #a855f7, #ec4899); background-size: 200% 100%; opacity:.9;"></span>
                    <span class="relative grid h-14 w-14 shrink-0 animate-wiggle place-items-center rounded-2xl bg-white/25 text-3xl shadow-soft">{{ $cat->icon }}</span>
                    <span class="relative">
                        <span class="inline-block rounded-full bg-white/25 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide">✨ Nouveau</span>
                        <span class="mt-1 block text-base font-extrabold leading-tight">{{ $cat->name }}</span>
                        <span class="text-xs font-medium text-white/90">{{ $cat->products_count }} produits · à découvrir →</span>
                    </span>
                </a>
            @else
                <a href="{{ route('category', $cat->slug) }}"
                   class="group relative flex flex-col items-center gap-3 overflow-hidden rounded-2xl p-5 text-center shadow-soft ring-1 ring-slate-100 transition hover:-translate-y-1"
                   style="background: linear-gradient(160deg, {{ $cat->color }}14, #ffffff);">
                    <span class="grid h-14 w-14 place-items-center rounded-2xl text-3xl shadow-soft" style="background: {{ $cat->color }}1f;">{{ $cat->icon }}</span>
                    <span class="text-sm font-semibold text-ink-900">{{ $cat->name }}</span>
                    <span class="text-xs text-slate-400">{{ $cat->products_count }} produits</span>
                </a>
            @endif
        @endforeach
    </div>
</section>

{{-- ✨ Special showcase: Games for Kids --}}
@if (!empty($kidsCategory) && $kidsProducts->isNotEmpty())
<section class="container-x py-8">
    <div class="relative overflow-hidden rounded-3xl p-6 sm:p-8 text-white shadow-card"
         style="background: linear-gradient(120deg, #7c3aed, #ec4899, #f59e0b);">
        {{-- Floating playful emojis --}}
        <span class="pointer-events-none absolute -top-2 right-6 text-5xl opacity-30 animate-float">🎈</span>
        <span class="pointer-events-none absolute bottom-2 right-24 text-4xl opacity-30 animate-float" style="animation-delay:.6s">🧩</span>
        <span class="pointer-events-none absolute top-8 right-44 text-3xl opacity-30 animate-float" style="animation-delay:1.2s">⭐</span>

        <div class="relative flex flex-wrap items-end justify-between gap-3">
            <div>
                <span class="inline-block rounded-full bg-white/25 px-3 py-1 text-xs font-bold uppercase tracking-wide">🧸 {{ app()->getLocale()==='ar' ? 'جديد' : 'Nouveau' }}</span>
                <h2 class="mt-2 font-display text-2xl font-extrabold sm:text-3xl">
                    {{ app()->getLocale()==='ar' ? 'ألعاب الأطفال' : 'Jeux pour Enfants' }}
                </h2>
                <p class="mt-1 max-w-md text-sm text-white/90">
                    {{ app()->getLocale()==='ar'
                        ? 'ألعاب وجوائز تعليمية ممتعة — تعلّم وأنت تلعب. الدفع عند الاستلام.'
                        : "Jeux, puzzles et jouets éducatifs — apprendre en s'amusant. Paiement à la livraison." }}
                </p>
            </div>
            <a href="{{ route('category', $kidsCategory->slug) }}"
               class="btn bg-white text-ink-900 hover:bg-white/90 shadow-soft">{{ __('shop.view_all') }} →</a>
        </div>

        <div class="relative mt-6 grid grid-cols-2 gap-4 sm:grid-cols-4">
            @foreach ($kidsProducts->take(4) as $kid)
                <a href="{{ route('product', $kid->slug) }}"
                   class="group rounded-2xl bg-white/95 p-2.5 text-ink-900 shadow-soft ring-1 ring-white/40 transition hover:-translate-y-1">
                    <div class="relative aspect-square overflow-hidden rounded-xl bg-slate-100">
                        <img src="{{ $kid->card_image_url }}"
                             @if ($kid->card_srcset) srcset="{{ $kid->card_srcset }}" sizes="(min-width:640px) 22vw, 45vw" @endif
                             alt="{{ $kid->display_name }}" width="300" height="300" loading="lazy" decoding="async"
                             class="h-full w-full object-cover transition duration-500 group-hover:scale-105">
                        @if ($kid->on_sale)
                            <span class="absolute start-2 top-2 badge bg-accent text-white">-{{ $kid->discount_percent }}%</span>
                        @endif
                    </div>
                    <h3 class="mt-2 line-clamp-1 px-1 text-sm font-semibold">{{ $kid->display_name }}</h3>
                    <p class="px-1 pb-1 text-sm font-bold text-brand-700">@money($kid->price)</p>
                </a>
            @endforeach
        </div>
    </div>
</section>
@endif

@include('partials.product-row', ['title' => __('shop.featured'), 'products' => $featured])
@if ($onSale->isNotEmpty())
    @include('partials.product-row', ['title' => __('shop.on_sale'), 'products' => $onSale])
@endif
@include('partials.product-row', ['title' => __('shop.new_arrivals'), 'products' => $newArrivals])

{{-- CTA --}}
<section class="container-x pb-12">
    <div class="rounded-3xl bg-ink-900 px-6 py-10 text-center text-white sm:px-12">
        <h2 class="font-display text-2xl font-bold sm:text-3xl">Commandez en quelques clics</h2>
        <p class="mx-auto mt-2 max-w-lg text-slate-300">Pas besoin de carte bancaire — payez en espèces à la réception de votre colis.</p>
        <a href="{{ route('catalog') }}" class="btn-accent mt-6">{{ __('shop.shop_now') }}</a>
    </div>
</section>
@endsection
