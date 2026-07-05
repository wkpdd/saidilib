@extends('layouts.app')
@section('title', $category->name . ' — ' . \App\Models\Setting::get('store_name'))
@section('meta_description', $category->description ?: $category->name)

@section('content')
<div class="container-x py-6">

    {{-- ── Path bar (breadcrumb, Explorer style) ───────────────────────── --}}
    <nav class="flex flex-wrap items-center gap-1 rounded-2xl bg-white px-3 py-2 text-sm shadow-soft ring-1 ring-slate-100">
        <a href="{{ route('home') }}" class="flex items-center gap-1 rounded-lg px-2.5 py-1 font-medium text-slate-500 hover:bg-slate-100 hover:text-brand-700">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 11l9-8 9 8M5 10v10a1 1 0 001 1h4v-6h4v6h4a1 1 0 001-1V10"/></svg>
            {{ __('shop.home') }}
        </a>
        @foreach ($ancestors as $anc)
            <span class="text-slate-300">›</span>
            <a href="{{ route('category', $anc->slug) }}" class="rounded-lg px-2.5 py-1 font-medium text-slate-500 hover:bg-slate-100 hover:text-brand-700">
                <span>{{ $anc->icon }}</span> {{ $anc->name }}
            </a>
        @endforeach
        <span class="text-slate-300">›</span>
        <span class="rounded-lg px-2.5 py-1 font-semibold text-ink-900">{{ $category->icon }} {{ $category->name }}</span>
    </nav>

    {{-- ── Category header ──────────────────────────────────────────────── --}}
    <div class="mt-5 flex items-center gap-4 rounded-3xl p-6 text-white shadow-card"
         style="background: linear-gradient(120deg, {{ $category->color }}, {{ $category->color }}cc);">
        <span class="grid h-16 w-16 shrink-0 place-items-center rounded-2xl bg-white/20 text-4xl">{{ $category->icon ?: '📁' }}</span>
        <div>
            <h1 class="font-display text-2xl font-extrabold sm:text-3xl">{{ $category->name }}</h1>
            @if ($category->description)
                <p class="mt-1 max-w-2xl text-sm text-white/90">{{ $category->description }}</p>
            @endif
            <p class="mt-1 text-xs font-medium text-white/80">
                {{ $category->children->count() }} sous-catégorie(s) · {{ $totalCount }} produit(s)
            </p>
        </div>
    </div>

    {{-- ── Folders: subcategories grid ──────────────────────────────────── --}}
    @if ($category->children->isNotEmpty())
        <div class="mt-8">
            <h2 class="mb-3 flex items-center gap-2 font-display text-lg font-bold text-ink-900">
                <span>🗂️</span> Sous-catégories
            </h2>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                @foreach ($category->children as $sub)
                    <a href="{{ route('category', $sub->slug) }}"
                       class="group relative flex items-center gap-3 overflow-hidden rounded-xl bg-white p-3 shadow-soft ring-1 ring-slate-100 transition hover:-translate-y-0.5 hover:shadow-card">
                        {{-- folder tab accent --}}
                        <span class="absolute inset-y-0 start-0 w-1.5" style="background: {{ $sub->color }}"></span>
                        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl text-2xl" style="background: {{ $sub->color }}1f;">{{ $sub->icon ?: '📁' }}</span>
                        <span class="min-w-0">
                            <span class="block truncate text-sm font-semibold text-ink-900">{{ $sub->name }}</span>
                            <span class="text-xs text-slate-400">{{ $sub->products_count }} produits</span>
                        </span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    {{-- ── Files: products grid ─────────────────────────────────────────── --}}
    <div class="mt-8">
        <div class="mb-3 flex items-center justify-between">
            <h2 class="flex items-center gap-2 font-display text-lg font-bold text-ink-900">
                <span>📦</span> {{ __('shop.products') ?? 'Produits' }}
                <span class="text-sm font-normal text-slate-400">({{ $products->total() }})</span>
            </h2>
            @if ($products->total() > 1)
                <form method="get" class="text-sm">
                    <select name="sort" onchange="this.form.submit()" class="input py-1.5 text-sm">
                        <option value="">Tri : en vedette</option>
                        <option value="newest" @selected(request('sort')==='newest')>Nouveautés</option>
                        <option value="price_asc" @selected(request('sort')==='price_asc')>Prix croissant</option>
                        <option value="price_desc" @selected(request('sort')==='price_desc')>Prix décroissant</option>
                    </select>
                </form>
            @endif
        </div>

        @if ($products->isNotEmpty())
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                @foreach ($products as $product)
                    <x-product-card :product="$product" />
                @endforeach
            </div>
            <div class="mt-6">{{ $products->links() }}</div>
        @elseif ($category->children->isNotEmpty())
            <div class="rounded-2xl bg-slate-50 px-4 py-10 text-center text-sm text-slate-500">
                Ouvrez une sous-catégorie ci-dessus pour voir les produits. 📂
            </div>
        @else
            <div class="rounded-2xl bg-slate-50 px-4 py-10 text-center text-sm text-slate-500">
                Aucun produit dans cette catégorie pour le moment.
                <a href="{{ route('catalog') }}" class="font-semibold text-brand-700 hover:underline">Voir toute la boutique →</a>
            </div>
        @endif
    </div>
</div>
@endsection
