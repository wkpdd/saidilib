@extends('admin.layout')
@section('title', 'Tableau de bord')
@section('heading', 'Tableau de bord')

@section('content')
@php use App\Support\Money; @endphp
<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
    @php
        $cards = [
            ['Commandes', $stats['orders_total'], '🧾', 'brand'],
            ['En attente', $stats['orders_pending'], '⏳', 'amber'],
            ['Chiffre d\'affaires', Money::format($stats['revenue']), '💰', 'green'],
            ['Produits', $stats['products'], '📦', 'indigo'],
        ];
    @endphp
    @foreach ($cards as [$label, $value, $icon, $color])
        <div class="card p-5">
            <div class="flex items-center justify-between">
                <span class="text-sm text-slate-500">{{ $label }}</span>
                <span class="text-2xl">{{ $icon }}</span>
            </div>
            <p class="mt-2 text-2xl font-bold">{{ $value }}</p>
        </div>
    @endforeach
</div>

<div class="mt-6 grid gap-6 lg:grid-cols-3">
    {{-- Sales chart --}}
    <div class="card p-5 lg:col-span-2">
        <h2 class="mb-4 font-semibold">Commandes (14 derniers jours)</h2>
        <div class="flex h-40 items-end gap-1">
            @php $max = max(array_merge([1], $chart)); @endphp
            @foreach ($chart as $day => $count)
                <div class="group flex flex-1 flex-col items-center justify-end gap-1">
                    <span class="text-[10px] text-slate-400">{{ $count ?: '' }}</span>
                    <div class="w-full rounded-t bg-brand-500/80 transition group-hover:bg-brand-600" style="height: {{ max(4, ($count / $max) * 100) }}%"></div>
                    <span class="text-[9px] text-slate-400">{{ \Illuminate\Support\Carbon::parse($day)->format('d/m') }}</span>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Top products --}}
    <div class="card p-5">
        <h2 class="mb-4 font-semibold">Produits populaires</h2>
        <ul class="space-y-3 text-sm">
            @forelse ($topProducts as $p)
                <li class="flex items-center justify-between">
                    <a href="{{ route('admin.products.edit', $p) }}" class="truncate hover:text-brand-700">{{ $p->name_fr }}</a>
                    <span class="text-slate-400">{{ $p->views }} vues</span>
                </li>
            @empty
                <li class="text-slate-400">Aucun produit.</li>
            @endforelse
        </ul>
    </div>
</div>

{{-- Recent orders --}}
<div class="card mt-6 overflow-hidden">
    <div class="flex items-center justify-between border-b border-slate-100 p-5">
        <h2 class="font-semibold">Dernières commandes</h2>
        <a href="{{ route('admin.orders.index') }}" class="text-sm font-semibold text-brand-700 hover:underline">Tout voir →</a>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-start text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-5 py-3 text-start">Réf.</th>
                    <th class="px-5 py-3 text-start">Client</th>
                    <th class="px-5 py-3 text-start">Wilaya</th>
                    <th class="px-5 py-3 text-start">Total</th>
                    <th class="px-5 py-3 text-start">Statut</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($recentOrders as $o)
                    <tr class="hover:bg-slate-50">
                        <td class="px-5 py-3"><a href="{{ route('admin.orders.show', $o) }}" class="font-semibold text-brand-700">{{ $o->reference }}</a></td>
                        <td class="px-5 py-3">{{ $o->customer_name }}<div class="text-xs text-slate-400">{{ $o->phone }}</div></td>
                        <td class="px-5 py-3">{{ optional($o->wilaya)->name }}</td>
                        <td class="px-5 py-3 font-semibold">@money($o->total)</td>
                        <td class="px-5 py-3"><span class="badge bg-{{ $o->status_color }}-50 text-{{ $o->status_color }}-700">{{ $o->status }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-10 text-center text-slate-400">Aucune commande pour le moment.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
