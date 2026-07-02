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
        {{-- Sidebar filters --}}
        <aside class="space-y-5">
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

            <form action="{{ url()->current() }}" method="get" class="card p-5">
                @foreach (request()->except(['min','max','page']) as $k => $v)
                    <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                @endforeach
                <h3 class="mb-3 font-semibold">{{ __('shop.price') }} (DA)</h3>
                <div class="flex items-center gap-2">
                    <input type="number" name="min" value="{{ request('min') }}" placeholder="Min" class="input">
                    <span class="text-slate-400">—</span>
                    <input type="number" name="max" value="{{ request('max') }}" placeholder="Max" class="input">
                </div>
                <button class="btn-primary mt-3 w-full">{{ __('shop.filter') }}</button>
            </form>
        </aside>

        {{-- Results --}}
        <div>
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-slate-500">{{ $products->total() }} {{ __('shop.results') }}</p>
                <form method="get" class="flex items-center gap-2 text-sm">
                    @foreach (request()->except(['sort','page']) as $k => $v)
                        <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                    @endforeach
                    <label class="text-slate-500">{{ __('shop.sort') }}</label>
                    <select name="sort" onchange="this.form.submit()" class="input w-auto py-1.5">
                        <option value="featured" @selected(request('sort')==='featured')>{{ __('shop.sort_featured') }}</option>
                        <option value="newest" @selected(request('sort')==='newest')>{{ __('shop.sort_newest') }}</option>
                        <option value="price_asc" @selected(request('sort')==='price_asc')>{{ __('shop.sort_price_asc') }}</option>
                        <option value="price_desc" @selected(request('sort')==='price_desc')>{{ __('shop.sort_price_desc') }}</option>
                    </select>
                </form>
            </div>

            @if ($products->isEmpty())
                <div class="card grid place-items-center py-20 text-center">
                    <span class="text-5xl">🔍</span>
                    <p class="mt-3 text-slate-500">{{ __('shop.no_products') }}</p>
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
