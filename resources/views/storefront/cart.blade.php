@extends('layouts.app')
@section('title', __('shop.my_cart'))

@section('content')
<div class="container-x py-8">
    <h1 class="mb-6 font-display text-2xl font-bold">{{ __('shop.my_cart') }}</h1>

    @if (empty($items))
        <div class="card grid place-items-center py-20 text-center">
            <span class="text-5xl">🛒</span>
            <p class="mt-3 text-slate-500">{{ __('shop.empty_cart') }}</p>
            <a href="{{ route('catalog') }}" class="btn-primary mt-5">{{ __('shop.continue') }}</a>
        </div>
    @else
        <div class="grid gap-6 lg:grid-cols-[1fr_360px]">
            <div class="space-y-4">
            @foreach ($groups as $group)
            @php $pack = $group['pack']; @endphp
            <div class="card divide-y divide-slate-100 {{ $pack ? 'ring-1 ring-brand-200' : '' }}">
                @if ($pack)
                    {{-- A pack is one product: its price only holds for the whole set. --}}
                    <div class="flex flex-wrap items-center gap-3 bg-brand-50/60 p-4">
                        <div class="min-w-0 flex-1">
                            <p class="font-display font-bold text-ink-900">🎒 {{ $pack['name'] }}</p>
                            <p class="text-xs text-slate-500">{{ __('shop.pack_locked_hint') }}</p>
                        </div>
                        <form action="{{ route('cart.update') }}" method="post" class="flex items-center gap-2">
                            @csrf @method('PATCH')
                            <input type="hidden" name="pack_id" value="{{ $pack['id'] }}">
                            <label class="text-xs text-slate-500">{{ __('shop.pack_qty') }}</label>
                            <input type="number" name="qty" value="{{ $pack['qty'] }}" min="1" max="99"
                                   onchange="this.form.submit()" class="input w-16 text-center">
                        </form>
                        <div class="font-bold text-brand-700">@money($pack['total'])</div>
                        <form action="{{ route('cart.remove') }}" method="post">
                            @csrf @method('DELETE')
                            <input type="hidden" name="pack_id" value="{{ $pack['id'] }}">
                            <button class="grid h-9 w-9 place-items-center rounded-lg text-slate-400 hover:bg-red-50 hover:text-red-600"
                                    title="{{ __('shop.remove_pack') }}">✕</button>
                        </form>
                    </div>
                @endif

                @foreach ($group['lines'] as $key => $line)
                    <div class="flex items-center gap-4 p-4">
                        <img src="{{ $line['image'] }}" class="h-20 w-20 shrink-0 rounded-xl object-cover ring-1 ring-slate-100" alt="">
                        <div class="min-w-0 flex-1">
                            <a href="{{ route('product', $line['slug']) }}" class="font-semibold text-ink-900 hover:text-brand-700">{{ $line['name'] }}</a>
                            @if ($line['variant'])<p class="text-xs text-slate-500">{{ $line['variant'] }}</p>@endif
                            <p class="mt-1 flex flex-wrap items-center gap-2 text-sm font-bold text-brand-700">
                                @money($line['price'])
                                @if (($line['discount_percent'] ?? 0) > 0)
                                    <span class="text-xs font-normal text-slate-400 line-through">@money($line['base_price'])</span>
                                    <span class="badge bg-accent text-white">-{{ rtrim(rtrim(number_format($line['discount_percent'], 2, ',', ''), '0'), ',') }}%</span>
                                @endif
                            </p>
                            @if (! empty($line['next_tier']))
                                <p class="mt-1 text-xs text-brand-700">
                                    ⬆️ {{ __('shop.qty_next_tier', [
                                        'n'       => $line['next_tier']['missing'],
                                        'percent' => rtrim(rtrim(number_format($line['next_tier']['percent'], 2, ',', ''), '0'), ','),
                                    ]) }}
                                </p>
                            @endif
                        </div>
                        @if ($pack)
                            {{-- Fixed quantity: it comes from the pack's composition. --}}
                            <div class="w-16 text-center text-sm font-semibold text-slate-500">×{{ $line['qty'] }}</div>
                            <div class="hidden w-24 text-end font-bold sm:block">@money($line['line_total'])</div>
                        @else
                            <form action="{{ route('cart.update') }}" method="post" class="flex items-center">
                                @csrf @method('PATCH')
                                <input type="hidden" name="key" value="{{ $key }}">
                                <input type="number" name="qty" value="{{ $line['qty'] }}" min="0"
                                       onchange="this.form.submit()" class="input w-16 text-center">
                            </form>
                            <div class="hidden w-24 text-end font-bold sm:block">@money($line['line_total'])</div>
                            <form action="{{ route('cart.remove') }}" method="post">
                                @csrf @method('DELETE')
                                <input type="hidden" name="key" value="{{ $key }}">
                                <button class="grid h-9 w-9 place-items-center rounded-lg text-slate-400 hover:bg-red-50 hover:text-red-600" title="{{ __('shop.remove') }}">✕</button>
                            </form>
                        @endif
                    </div>
                @endforeach
            </div>
            @endforeach
            </div>

            {{-- Summary --}}
            <div class="h-fit card p-5">
                <h2 class="mb-4 font-semibold">{{ __('shop.order_summary') }}</h2>
                <div class="flex justify-between py-1.5 text-sm">
                    <span class="text-slate-500">{{ __('shop.subtotal') }}</span>
                    <span class="font-semibold">@money($subtotal)</span>
                </div>
                @if (($savings ?? 0) > 0)
                    <div class="flex justify-between py-1.5 text-sm">
                        <span class="text-accent">💼 {{ __('shop.qty_savings') }}</span>
                        <span class="font-semibold text-accent">−@money($savings)</span>
                    </div>
                @endif
                <div class="flex justify-between py-1.5 text-sm">
                    <span class="text-slate-500">{{ __('shop.delivery') }}</span>
                    @if (app(\App\Services\CartService::class)->hasFreeShipping())
                        <span class="font-semibold text-green-600">🚚 {{ __('shop.free_shipping') }}</span>
                    @else
                        <span class="text-slate-400">{{ app()->getLocale()==='ar' ? 'يُحتسب عند الطلب' : 'Calculé à la commande' }}</span>
                    @endif
                </div>
                <div class="mt-3 flex justify-between border-t border-slate-100 pt-3 text-lg font-bold">
                    <span>{{ __('shop.total') }}</span>
                    <span class="text-brand-700">@money($subtotal)</span>
                </div>
                <a href="{{ route('checkout.index') }}" class="btn-accent mt-5 w-full">{{ __('shop.checkout') }}</a>
                <a href="{{ \App\Support\ShopTrail::resumeUrl() }}" class="btn-ghost mt-2 w-full">{{ app()->getLocale() === 'ar' ? '→' : '←' }} {{ __('shop.continue') }}</a>
            </div>
        </div>
    @endif
</div>
@endsection
