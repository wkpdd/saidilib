@extends('layouts.app')
@section('title', $product->name . ' — ' . \App\Models\Setting::get('store_name'))
@section('meta_description', $product->short_desc)

@push('head')
    {{-- Open Graph / Twitter cards: make shared links render as rich posts --}}
    <meta property="og:type" content="product">
    <meta property="og:site_name" content="{{ \App\Models\Setting::get('store_name', 'Saidi Papetrie') }}">
    <meta property="og:title" content="{{ $product->name }}">
    <meta property="og:description" content="{{ $product->short_desc ?: \Illuminate\Support\Str::limit(strip_tags($product->description), 150) }}">
    <meta property="og:image" content="{{ $product->main_image_url }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="product:price:amount" content="{{ (float) $product->price }}">
    <meta property="product:price:currency" content="{{ \App\Models\Setting::get('currency', 'DA') }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $product->name }}">
    <meta name="twitter:description" content="{{ $product->short_desc }}">
    <meta name="twitter:image" content="{{ $product->main_image_url }}">
@endpush

@section('content')
<div class="container-x py-8">
    <nav class="mb-5 text-sm text-slate-500">
        <a href="{{ route('home') }}" class="hover:text-brand-700">{{ __('shop.home') }}</a>
        <span class="mx-1">/</span>
        @if ($product->category)
            <a href="{{ route('category', $product->category->slug) }}" class="hover:text-brand-700">{{ $product->category->name }}</a>
            <span class="mx-1">/</span>
        @endif
        <span class="text-ink-900">{{ $product->name }}</span>
    </nav>

    <div class="grid gap-8 lg:grid-cols-2">
        {{-- Gallery --}}
        <div>
            <div class="card overflow-hidden">
                <img data-main-image src="{{ $product->main_image_url }}" alt="{{ $product->name }}"
                     class="aspect-square w-full bg-slate-100 object-cover">
            </div>
            @if ($product->images->count() > 1)
                <div class="mt-3 flex gap-2 overflow-x-auto pb-1">
                    @foreach ($product->images as $img)
                        <button type="button" data-thumb="{{ $img->url }}"
                            class="h-20 w-20 shrink-0 overflow-hidden rounded-xl ring-1 ring-slate-200 {{ $loop->first ? 'ring-2 ring-brand-500' : '' }}">
                            <img src="{{ $img->url }}" class="h-full w-full object-cover" alt="">
                        </button>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Buy box --}}
        <div>
            @if ($product->category)
                <a href="{{ route('category', $product->category->slug) }}" class="text-sm font-semibold uppercase tracking-wide text-brand-600">{{ $product->category->name }}</a>
            @endif
            <h1 class="mt-1 font-display text-2xl font-bold sm:text-3xl">{{ $product->name }}</h1>

            <div class="mt-3 flex flex-wrap items-center gap-3 text-sm text-slate-500">
                @if ($product->brand)<span>{{ __('shop.brand') }}: <b class="text-ink-700">{{ $product->brand }}</b></span>@endif
                @if ($product->sku)<span>{{ __('shop.reference') }}: <b class="text-ink-700">{{ $product->sku }}</b></span>@endif
                @if ($product->in_stock)
                    <span class="badge bg-green-50 text-green-700">● {{ __('shop.in_stock') }}</span>
                @else
                    <span class="badge bg-red-50 text-red-700">● {{ __('shop.out_of_stock') }}</span>
                @endif
            </div>

            <div class="mt-4 flex flex-wrap items-end gap-3">
                <span data-price data-price="{{ (float) $product->current_price }}" data-currency="{{ \App\Models\Setting::get('currency','DA') }}"
                      class="text-3xl font-extrabold text-ink-900">@money($product->current_price)</span>
                @if ($product->has_tier_price)
                    <span class="text-lg text-slate-400 line-through">@money($product->price)</span>
                    <span class="badge bg-brand-700 text-white">💼 {{ auth('client')->user()->type_label }}</span>
                @elseif ($product->on_sale)
                    <span class="text-lg text-slate-400 line-through">@money($product->compare_at_price)</span>
                    <span class="badge bg-accent text-white">-{{ $product->discount_percent }}%</span>
                @endif
            </div>

            @if ($product->short_desc)
                <p class="mt-4 text-sm leading-relaxed text-ink-700">{{ $product->short_desc }}</p>
            @endif

            <form action="{{ route('cart.add') }}" method="post" class="mt-6 space-y-5">
                @csrf
                <input type="hidden" name="product_id" value="{{ $product->id }}">

                {{-- Colour / size selection with live availability --}}
                @if ($product->variants->isNotEmpty())
                    @php
                        // Colour NAME is optional — the hex code is the stable identity
                        // (never blank for a colour row), so match/dedupe on that.
                        $colors = $product->variants->filter(fn ($v) => $v->color_hex)->unique('color_hex')->values();
                        $sizes  = $product->variants->filter(fn ($v) => $v->size)->unique('size')->values();
                        $variantData = $product->variants->map(fn ($v) => [
                            'id'    => $v->id,
                            'color' => $v->color_hex,
                            'size'  => $v->size,
                            'stock' => (int) $v->stock,
                            'delta' => (float) $v->price_delta,
                            'image' => $v->image?->url,
                        ])->values();
                    @endphp
                    <div data-variants='@json($variantData)'
                         data-base-price="{{ (float) $product->current_price }}"
                         data-currency="{{ \App\Models\Setting::get('currency','DA') }}"
                         data-track-stock="{{ $product->track_stock ? 1 : 0 }}"
                         class="space-y-4">
                        @if ($colors->isNotEmpty())
                            <div>
                                <label class="label">{{ __('shop.color') }} : <span data-color-label class="font-semibold text-ink-900"></span></label>
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($colors as $c)
                                        <button type="button" data-color="{{ $c->color_hex }}" data-color-name="{{ $c->color ?: $c->color_hex }}"
                                                title="{{ $c->color ?: $c->color_hex }}"
                                                class="grid h-9 w-9 place-items-center rounded-full ring-2 ring-transparent transition"
                                                style="background: {{ $c->color_hex }}"></button>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @if ($sizes->isNotEmpty())
                            <div>
                                <label class="label">{{ __('shop.choose_size') }}</label>
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($sizes as $s)
                                        <button type="button" data-size="{{ $s->size }}"
                                                class="badge border border-slate-200 px-4 py-2 text-sm font-semibold text-ink-700 transition">
                                            {{ $s->size }}
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <p data-availability class="text-sm text-slate-500"></p>
                        <input type="hidden" name="variant_id" data-variant-input>
                    </div>
                @endif

                {{-- Quantity --}}
                <div>
                    <label class="label">{{ __('shop.quantity') }}</label>
                    <div data-qty class="inline-flex items-center rounded-xl ring-1 ring-slate-200">
                        <button type="button" data-dec class="grid h-10 w-10 place-items-center text-lg hover:bg-slate-50">−</button>
                        <input type="number" name="qty" value="1" min="1" class="h-10 w-14 border-0 text-center focus:ring-0">
                        <button type="button" data-inc class="grid h-10 w-10 place-items-center text-lg hover:bg-slate-50">+</button>
                    </div>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row">
                    <button type="submit" class="btn-primary flex-1">🛒 {{ __('shop.add_to_cart') }}</button>
                    <button type="submit" formaction="{{ route('cart.add') }}" class="btn-accent flex-1"
                            onclick="this.form.querySelector('[name=buy_now]')?.remove()">⚡ {{ __('shop.buy_now') }}</button>
                </div>

                <div class="grid grid-cols-3 gap-2 pt-2 text-center text-xs text-slate-500">
                    <div class="rounded-xl bg-slate-50 p-3">🚚<br>58 wilayas</div>
                    <div class="rounded-xl bg-slate-50 p-3">💵<br>{{ __('shop.cod') }}</div>
                    <div class="rounded-xl bg-slate-50 p-3">↩️<br>Retour facile</div>
                </div>
            </form>

            @include('partials.share', ['product' => $product])
        </div>
    </div>

    {{-- Description --}}
    @if ($product->description)
        <div class="card mt-10 p-6 lg:p-8">
            <h2 class="mb-3 font-display text-xl font-bold">{{ __('shop.description') }}</h2>
            <div class="prose prose-sm max-w-none text-ink-700">{!! $product->description !!}</div>
        </div>
    @endif

    {{-- Related --}}
    @if ($related->isNotEmpty())
        <div class="mt-12">
            <h2 class="mb-5 font-display text-2xl font-bold">{{ __('shop.related') }}</h2>
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                @foreach ($related as $product)
                    <x-product-card :product="$product" />
                @endforeach
            </div>
        </div>
    @endif
</div>
@endsection
