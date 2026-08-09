@extends('layouts.app')
@section('title', __('shop.all_products') . ' — ' . \App\Models\Setting::get('store_name'))

@section('content')
<div class="container-x py-8">
    <nav class="mb-4 text-sm text-slate-500">
        <a href="{{ route('home') }}" class="hover:text-brand-700">{{ __('shop.home') }}</a>
        <span class="mx-1">/</span>
        <span class="text-ink-900">{{ ($activeCategory ?? null)?->name ?? __('shop.all_products') }}</span>
    </nav>

    <div class="grid gap-6 lg:grid-cols-[260px_1fr]">
        {{-- Sidebar filters (mobile gets the same panel in a drawer) --}}
        <aside class="hidden space-y-5 lg:block">
            <div class="card p-5">
                <h3 class="mb-3 font-semibold">{{ __('shop.categories') }}</h3>
                <ul class="space-y-1 text-sm">
                    <li><a href="{{ route('catalog') }}" class="block rounded-lg px-3 py-1.5 {{ !request('category') ? 'bg-brand-50 font-semibold text-brand-700' : 'hover:bg-slate-50' }}">{{ __('shop.all_products') }}</a></li>
                    @foreach ($categories as $cat)
                        <li>
                            <a href="{{ route('category', $cat->slug) }}"
                               class="flex items-center justify-between rounded-lg px-3 py-1.5 {{ request('category')===$cat->slug ? 'bg-brand-50 font-semibold text-brand-700' : 'hover:bg-slate-50' }}">
                                <span>{{ $cat->icon }} {{ $cat->name }}</span>
                                <span class="text-xs text-slate-400">{{ $cat->products_count }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="card p-5">
                <h3 class="mb-3 font-semibold">{{ __('shop.filters') }}</h3>
                @include('partials.product-filters', ['filter' => $filter])
            </div>
        </aside>

        {{-- Results --}}
        <div>
            @include('partials.product-filter-bar', ['filter' => $filter, 'products' => $products])

            @if ($products->isEmpty())
                <div class="card grid place-items-center py-20 text-center">
                    <span class="text-5xl">🔍</span>
                    <p class="mt-3 text-slate-500">{{ __('shop.no_products') }}</p>
                    @if ($filter->isActive())
                        <a href="{{ $filter->resetUrl() }}" class="btn-ghost mt-4">{{ __('shop.filter_reset') }}</a>
                    @endif
                </div>
            @else
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
                    @foreach ($products as $product)
                        <x-product-card :product="$product" />
                    @endforeach
                </div>
                <div class="mt-8">{{ $products->links() }}</div>
            @endif
        </div>
    </div>
</div>
@endsection
