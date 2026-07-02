@extends('admin.layout')
@section('title', 'Commandes')
@section('heading', 'Commandes')

@section('content')
<div class="mb-4 flex flex-wrap items-center gap-2">
    <a href="{{ route('admin.orders.index') }}" class="badge {{ !request('status') ? 'bg-brand-600 text-white' : 'bg-white ring-1 ring-slate-200' }} px-3 py-1.5">Toutes</a>
    @foreach (\App\Models\Order::STATUSES as $st)
        <a href="{{ route('admin.orders.index', ['status' => $st]) }}"
           class="badge px-3 py-1.5 {{ request('status')===$st ? 'bg-brand-600 text-white' : 'bg-white ring-1 ring-slate-200' }}">
            {{ $st }} <span class="ms-1 opacity-70">{{ $counts[$st] ?? 0 }}</span>
        </a>
    @endforeach
    <form class="ms-auto" method="get">
        <input type="hidden" name="status" value="{{ request('status') }}">
        <input name="q" value="{{ request('q') }}" placeholder="Réf, nom, téléphone…" class="input w-64">
    </form>
</div>

<div class="card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3 text-start">Réf.</th>
                    <th class="px-4 py-3 text-start">Client</th>
                    <th class="px-4 py-3 text-start">Wilaya / Livraison</th>
                    <th class="px-4 py-3 text-start">Total</th>
                    <th class="px-4 py-3 text-start">Livreur</th>
                    <th class="px-4 py-3 text-start">Statut</th>
                    <th class="px-4 py-3 text-start">Date</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($orders as $o)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3"><a href="{{ route('admin.orders.show', $o) }}" class="font-semibold text-brand-700">{{ $o->reference }}</a></td>
                        <td class="px-4 py-3">{{ $o->customer_name }}<div class="text-xs text-slate-400">{{ $o->phone }}</div></td>
                        <td class="px-4 py-3">{{ optional($o->wilaya)->name }}<div class="text-xs text-slate-400">{{ $o->delivery_type === 'home' ? 'À domicile' : 'Stop desk' }}</div></td>
                        <td class="px-4 py-3 font-semibold">@money($o->total)</td>
                        <td class="px-4 py-3">{{ $o->delivery_provider ? ucfirst($o->delivery_provider) : '—' }}@if($o->tracking_number)<div class="text-xs text-slate-400">{{ $o->tracking_number }}</div>@endif</td>
                        <td class="px-4 py-3"><span class="badge bg-{{ $o->status_color }}-50 text-{{ $o->status_color }}-700">{{ $o->status }}</span></td>
                        <td class="px-4 py-3 text-slate-400">{{ $o->created_at->format('d/m H:i') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-12 text-center text-slate-400">Aucune commande.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-4">{{ $orders->links() }}</div>
@endsection
