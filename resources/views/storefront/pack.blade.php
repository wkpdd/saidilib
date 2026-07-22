@extends('layouts.app')
@section('title', $pack->name . ' — ' . \App\Models\Setting::get('store_name'))
@section('meta_description', $pack->description)

@section('content')
<div class="container-x py-8">
    <nav class="mb-5 text-sm text-slate-500">
        <a href="{{ route('home') }}" class="hover:text-brand-700">{{ __('shop.home') }}</a>
        <span class="mx-1">/</span>
        <span class="text-ink-900">{{ $pack->name }}</span>
    </nav>

    <div class="grid gap-8 lg:grid-cols-2">
        <div>
            <div class="card overflow-hidden">
                @if ($pack->image_url)
                    <img src="{{ $pack->image_url }}" alt="{{ $pack->name }}" class="aspect-video w-full bg-slate-100 object-cover">
                @else
                    <div class="grid aspect-video w-full place-items-center bg-brand-50 text-6xl">🎒</div>
                @endif
            </div>
        </div>

        <div>
            <span class="badge bg-brand-50 text-brand-700">🎒 {{ __('shop.packs_badge') }}</span>
            <h1 class="mt-2 font-display text-2xl font-bold sm:text-3xl">{{ $pack->name }}</h1>
            @if ($pack->description)
                <p class="mt-3 text-sm leading-relaxed text-ink-700">{{ $pack->description }}</p>
            @endif

            <div class="mt-4 flex flex-wrap items-end gap-3">
                <span class="text-3xl font-extrabold text-ink-900">@money($pack->effective_price)</span>
                @if ($pack->has_promo)
                    <span class="text-lg text-slate-400 line-through">@money($pack->items_total)</span>
                    <span class="badge bg-accent text-white">{{ __('shop.pack_promo') }}</span>
                @endif
            </div>

            <form action="{{ route('pack.add', $pack->slug) }}" method="post" class="mt-6">
                @csrf
                <button class="btn-primary w-full py-3 text-base">🛒 {{ __('shop.add_pack_to_cart') }}</button>
            </form>
            <p class="mt-2 text-center text-xs text-slate-400">{{ __('shop.pack_note') }}</p>
        </div>
    </div>

    {{-- Contents --}}
    <div class="card mt-10 p-6">
        <h2 class="mb-4 font-display text-xl font-bold">📋 {{ __('shop.pack_contains') }} ({{ $pack->items->count() }})</h2>
        <div class="grid gap-3 sm:grid-cols-2">
            @foreach ($pack->items as $item)
                @if ($item->product)
                    <a href="{{ route('product', $item->product->slug) }}" class="flex items-center gap-3 rounded-xl p-2 ring-1 ring-slate-100 transition hover:ring-brand-300">
                        <img src="{{ $item->product->card_image_url }}" class="h-14 w-14 rounded-lg bg-slate-100 object-cover" alt="">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold">{{ $item->product->display_name }}</p>
                            @if ($item->variant)<p class="text-xs text-slate-400">{{ $item->variant->label_fr }}</p>@endif
                        </div>
                        <span class="badge bg-slate-100 text-ink-700">× {{ $item->quantity }}</span>
                    </a>
                @endif
            @endforeach
        </div>
    </div>
</div>
@endsection
