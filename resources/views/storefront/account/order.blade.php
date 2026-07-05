@extends('layouts.app')
@section('title', $order->reference)

@section('content')
<section class="container-x max-w-3xl py-10">
    <a href="{{ route('account.index') }}" class="text-sm font-semibold text-brand-700 hover:underline">← {{ __('shop.account') }}</a>

    <div class="mt-4 card p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="font-display text-xl font-bold">{{ $order->reference }}</h1>
            <span class="badge bg-{{ $order->status_color }}-100 text-{{ $order->status_color }}-700">{{ $order->status_label }}</span>
        </div>
        <p class="mt-1 text-sm text-slate-500">{{ $order->created_at->format('d/m/Y H:i') }}</p>

        <div class="mt-5 divide-y divide-slate-100">
            @foreach ($order->items as $item)
                <div class="flex items-center justify-between gap-3 py-2.5 text-sm">
                    <span>{{ $item->name }} @if($item->variant_label)<span class="text-slate-400">· {{ $item->variant_label }}</span>@endif</span>
                    <span class="text-slate-500">×{{ $item->quantity }}</span>
                    <span class="font-semibold">@money($item->line_total)</span>
                </div>
            @endforeach
        </div>

        <div class="mt-4 space-y-1 border-t border-slate-100 pt-4 text-sm">
            <div class="flex justify-between text-slate-500"><span>{{ __('shop.order_summary') }}</span><span>@money($order->subtotal)</span></div>
            <div class="flex justify-between text-slate-500"><span>{{ __('shop.delivery_type') }}</span><span>@money($order->delivery_fee)</span></div>
            <div class="flex justify-between text-base font-bold"><span>Total</span><span>@money($order->total)</span></div>
            @if ($order->is_refunded)
                <div class="flex justify-between text-green-600"><span>Remboursé</span><span>−@money($order->refund_amount)</span></div>
            @endif
        </div>
    </div>
</section>
@endsection
