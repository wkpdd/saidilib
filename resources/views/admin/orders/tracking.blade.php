@extends('admin.layout')
@section('title', 'Suivi des colis Noest')
@section('heading', '📍 Suivi des colis (Noest)')

@section('content')
<div class="mb-4 flex flex-wrap items-center gap-2">
    <form action="{{ route('admin.orders.tracking.refresh') }}" method="post">
        @csrf
        <button class="btn-primary">🔄 Actualiser tous les colis en cours</button>
    </form>
    <a href="{{ route('admin.orders.tracking', array_merge(request()->only('q'), ['all' => request()->boolean('all') ? null : 1])) }}"
       class="btn-ghost text-sm">
        {{ request()->boolean('all') ? '📦 Masquer les colis terminés' : '📦 Voir aussi les colis terminés' }}
    </a>
    <a href="{{ route('admin.orders.index') }}" class="btn-ghost text-sm">← Commandes</a>

    <form method="get" class="ms-auto">
        @if (request()->boolean('all'))<input type="hidden" name="all" value="1">@endif
        <input name="q" value="{{ request('q') }}" placeholder="Réf, tracking, client…" class="input w-56">
    </form>
</div>

<p class="mb-3 text-xs text-slate-400">
    Les statuts viennent de l'API Noest (<code>get/trackings/info</code>), 100 colis par actualisation.
    Une commande livrée ou retournée chez Noest passe automatiquement au même statut ici.
</p>

<div class="card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3 text-start">Commande</th>
                    <th class="px-4 py-3 text-start">Client / Wilaya</th>
                    <th class="px-4 py-3 text-start">Tracking</th>
                    <th class="px-4 py-3 text-start">Dernier événement Noest</th>
                    <th class="px-4 py-3 text-start">Livreur</th>
                    <th class="px-4 py-3 text-start">Statut local</th>
                    <th class="px-4 py-3 text-start">Vérifié</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($orders as $o)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3">
                            <a href="{{ route('admin.orders.show', $o) }}" class="font-semibold text-brand-700">{{ $o->reference }}</a>
                            <div class="text-xs text-slate-400">@money($o->total)</div>
                        </td>
                        <td class="px-4 py-3">{{ $o->customer_name }}<div class="text-xs text-slate-400">{{ optional($o->wilaya)->name }}</div></td>
                        <td class="px-4 py-3 font-mono text-xs">{{ $o->tracking_number }}</td>
                        <td class="px-4 py-3">
                            @if ($o->noest_status)
                                <span class="font-medium">{{ $o->noest_status }}</span>
                                @php $last = collect($o->noest_activity ?? [])->last(); @endphp
                                @if ($last && !empty($last['date']))<div class="text-xs text-slate-400">{{ $last['date'] }}</div>@endif
                            @else
                                <span class="text-slate-400">— jamais vérifié</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-xs">{{ $o->noest_driver ?: '—' }}</td>
                        <td class="px-4 py-3"><span class="badge bg-{{ $o->status_color }}-50 text-{{ $o->status_color }}-700">{{ $o->status_label }}</span></td>
                        <td class="px-4 py-3 text-xs text-slate-400">{{ $o->noest_checked_at?->diffForHumans() ?? '—' }}</td>
                        <td class="px-4 py-3 whitespace-nowrap text-end">
                            <form action="{{ route('admin.orders.tracking.one', $o) }}" method="post" class="inline">
                                @csrf
                                <button class="btn-ghost px-2 py-1 text-xs" title="Vérifier ce colis maintenant">🔄</button>
                            </form>
                            <a href="{{ route('admin.orders.noest.label', $o) }}" target="_blank" class="ms-1" title="Étiquette Noest">🏷️</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-12 text-center text-slate-400">Aucun colis Noest{{ request()->boolean('all') ? '' : ' en cours' }}.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-4">{{ $orders->links() }}</div>
@endsection
