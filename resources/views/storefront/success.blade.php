@extends('layouts.app')
@section('title', __('shop.thank_you'))

@section('content')
<div class="container-x py-16">
    <div class="mx-auto max-w-lg card p-8 text-center">
        <div class="mx-auto grid h-16 w-16 place-items-center rounded-full bg-green-100 text-3xl">✅</div>
        <h1 class="mt-5 font-display text-2xl font-bold">{{ __('shop.thank_you') }}</h1>
        <p class="mt-2 text-slate-500">{{ __('shop.order_received') }}</p>

        <div class="mt-6 rounded-xl bg-slate-50 p-4 text-start text-sm">
            <div class="flex justify-between py-1"><span class="text-slate-500">{{ __('shop.order_ref') }}</span><span class="font-bold text-brand-700">{{ $order->reference }}</span></div>
            <div class="flex justify-between py-1"><span class="text-slate-500">{{ __('shop.full_name') }}</span><span class="font-medium">{{ $order->customer_name }}</span></div>
            <div class="flex justify-between py-1"><span class="text-slate-500">{{ __('shop.wilaya') }}</span><span class="font-medium">{{ optional($order->wilaya)->name }}</span></div>
            <div class="flex justify-between py-1"><span class="text-slate-500">{{ __('shop.delivery') }}</span><span class="font-medium">@money($order->delivery_fee)</span></div>
            <div class="mt-1 flex justify-between border-t border-slate-200 pt-2 text-base font-bold"><span>{{ __('shop.total') }}</span><span class="text-brand-700">@money($order->total)</span></div>
        </div>

        <div class="mt-4 space-y-2 text-start">
            @foreach ($order->items as $it)
                <div class="flex justify-between text-sm">
                    <span>{{ $it->quantity }}× {{ $it->name }} @if($it->variant_label)<span class="text-slate-400">({{ $it->variant_label }})</span>@endif</span>
                    <span class="font-medium">@money($it->line_total)</span>
                </div>
            @endforeach
        </div>

        <a href="{{ route('home') }}" class="btn-primary mt-6">{{ __('shop.back_home') }}</a>
    </div>
</div>

@push('scripts')
{{-- Fire Purchase event on all active pixels --}}
<script>
    if (window.fbq) fbq('track', 'Purchase', {value: {{ (float) $order->total }}, currency: 'DZD', content_type: 'product'});
    if (window.ttq) ttq.track('CompletePayment', {value: {{ (float) $order->total }}, currency: 'DZD'});
    if (window.gtag) gtag('event', 'purchase', {value: {{ (float) $order->total }}, currency: 'DZD', transaction_id: @json($order->reference)});
    if (window.snaptr) snaptr('track', 'PURCHASE', {price: {{ (float) $order->total }}, currency: 'DZD'});
</script>
@endpush
@endsection
