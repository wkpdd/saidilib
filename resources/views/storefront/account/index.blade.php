@extends('layouts.app')
@section('title', __('shop.account'))

@section('content')
<section class="container-x py-10">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="font-display text-2xl font-bold">{{ __('shop.account') }}</h1>
            <p class="text-sm text-slate-500">{{ $client->name }} · {{ $client->email }}</p>
        </div>
        <form action="{{ route('account.logout') }}" method="post">
            @csrf
            <button class="btn-ghost">{{ __('shop.logout') }}</button>
        </form>
    </div>

    {{-- B2B price list download --}}
    @if ($client->type === 'wholesale')
        <a href="{{ route('account.pricelist') }}"
           class="mt-6 flex items-center justify-between gap-4 rounded-2xl bg-brand-600 px-5 py-4 text-white shadow-card transition hover:bg-brand-700">
            <div class="flex items-center gap-3">
                <span class="text-2xl">📄</span>
                <div>
                    <p class="font-semibold">Liste des prix (PDF)</p>
                    <p class="text-xs text-brand-100">Tarifs grossiste — tout le catalogue</p>
                </div>
            </div>
            <span class="badge bg-white/20 text-white">Télécharger ↓</span>
        </a>
    @endif

    {{-- Balance card (debt) --}}
    <div class="mt-6 grid gap-4 sm:grid-cols-3">
        <div class="card p-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ __('shop.account_balance') }}</p>
            <p class="mt-1 text-2xl font-bold {{ $balance > 0 ? 'text-red-600' : 'text-green-600' }}">@money($balance)</p>
            @if ($balance > 0)
                <p class="mt-1 text-xs text-red-500">{{ __('shop.you_owe') }}</p>
            @endif
        </div>
        <div class="card p-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ __('shop.my_orders') }}</p>
            <p class="mt-1 text-2xl font-bold">{{ $orders->count() }}</p>
        </div>
        <div class="card p-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Type</p>
            <p class="mt-1 text-lg font-semibold">{{ $client->type_label }}</p>
        </div>
    </div>

    {{-- Orders --}}
    <h2 class="mt-8 font-display text-lg font-bold">{{ __('shop.my_orders') }}</h2>
    <div class="mt-3 overflow-hidden rounded-2xl ring-1 ring-slate-100">
        @forelse ($orders as $order)
            <a href="{{ route('account.order', $order) }}"
               class="flex items-center justify-between gap-4 border-b border-slate-100 bg-white px-4 py-3 text-sm last:border-0 hover:bg-slate-50">
                <span class="font-semibold text-brand-700">{{ $order->reference }}</span>
                <span class="text-slate-500">{{ $order->created_at->format('d/m/Y') }}</span>
                <span class="badge bg-{{ $order->status_color }}-100 text-{{ $order->status_color }}-700">{{ $order->status_label }}</span>
                <span class="font-bold">@money($order->total)</span>
            </a>
        @empty
            <div class="bg-white px-4 py-8 text-center text-sm text-slate-400">{{ __('shop.no_orders') }}</div>
        @endforelse
    </div>

    {{-- Account ledger (debt history) --}}
    @if ($ledger->isNotEmpty())
        <h2 class="mt-8 font-display text-lg font-bold">{{ __('shop.account_ledger') }}</h2>
        <div class="mt-3 overflow-hidden rounded-2xl ring-1 ring-slate-100">
            @foreach ($ledger as $tx)
                <div class="flex items-center justify-between gap-4 border-b border-slate-100 bg-white px-4 py-3 text-sm last:border-0">
                    <span class="text-slate-500">{{ $tx->created_at->format('d/m/Y') }}</span>
                    <span class="flex-1 truncate">{{ $tx->description ?: \App\Models\ClientTransaction::TYPES[$tx->type] }}</span>
                    <span class="font-bold {{ $tx->type === 'payment' ? 'text-green-600' : 'text-red-600' }}">
                        {{ $tx->type === 'payment' ? '−' : '+' }}@money($tx->amount)
                    </span>
                </div>
            @endforeach
        </div>
    @endif
</section>
@endsection
