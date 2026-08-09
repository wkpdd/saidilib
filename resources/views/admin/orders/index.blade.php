@extends('admin.layout')
@section('title', 'Commandes')
@section('heading', 'Commandes')

@section('content')
<div class="mb-4 flex flex-wrap items-center gap-2">
    <a href="{{ route('admin.orders.index') }}" class="badge {{ !request('status') ? 'bg-brand-600 text-white' : 'bg-white ring-1 ring-slate-200' }} px-3 py-1.5">Toutes</a>
    @foreach (\App\Models\Order::STATUSES as $st)
        <a href="{{ route('admin.orders.index', ['status' => $st]) }}"
           class="badge px-3 py-1.5 {{ request('status')===$st ? 'bg-brand-600 text-white' : 'bg-white ring-1 ring-slate-200' }}">
            {{ \App\Models\Order::STATUS_LABELS[$st] ?? $st }} <span class="ms-1 opacity-70">{{ $counts[$st] ?? 0 }}</span>
        </a>
    @endforeach
    <div class="ms-auto flex items-center gap-2">
        <a href="{{ route('admin.orders.tracking') }}" class="btn-ghost text-sm">📍 Suivi des colis</a>
        <form method="get">
            <input type="hidden" name="status" value="{{ request('status') }}">
            <input name="q" value="{{ request('q') }}" placeholder="Réf, nom, téléphone…" class="input w-56">
        </form>
    </div>
</div>

{{-- Bulk actions — enabled as soon as a line is ticked. --}}
<div id="bulkBar" class="mb-3 hidden flex-wrap items-center gap-2 rounded-2xl bg-ink-900 px-4 py-3 text-sm text-white">
    <b id="bulkCount">0</b> commande(s) sélectionnée(s)

    <a id="bulkPrint" href="#" target="_blank" class="btn bg-white text-ink-900 hover:bg-slate-100">🖨️ Fiches de préparation</a>
    <label class="flex items-center gap-1.5 text-xs opacity-90">
        <input type="checkbox" id="markPreparing" class="rounded"> passer en « préparation »
    </label>

    <form action="{{ route('admin.orders.bulk.ready') }}" method="post" data-bulk-form class="inline"
          onsubmit="return confirm('Marquer ces commandes comme PRÊTES et les envoyer à Noest ?')">
        @csrf <input type="hidden" name="ids" data-ids>
        <button class="btn bg-green-600 text-white hover:bg-green-700">✅ Prêtes → Noest</button>
    </form>

    <form action="{{ route('admin.orders.labels') }}" method="post" data-bulk-form class="inline">
        @csrf <input type="hidden" name="ids" data-ids>
        <button class="btn bg-white/10 text-white ring-1 ring-white/30 hover:bg-white/20">🏷️ Étiquettes Noest (ZIP)</button>
    </form>

    <form action="{{ route('admin.orders.tracking.refresh') }}" method="post" data-bulk-form class="inline">
        @csrf <input type="hidden" name="ids" data-ids>
        <button class="btn bg-white/10 text-white ring-1 ring-white/30 hover:bg-white/20">🔄 Actualiser le suivi</button>
    </form>

    <a href="#" id="bulkClear" class="ms-auto text-xs underline opacity-80">tout décocher</a>
</div>

<div class="mb-3 text-xs text-slate-400">
    Astuce : cochez des commandes pour les actions groupées, ou
    <a href="{{ route('admin.orders.print', request()->only('status', 'q')) }}" target="_blank" class="underline">imprimez les fiches de tout le filtre courant</a>
    (200 max).
</div>

<div class="card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-3 py-3"><input type="checkbox" id="checkAll" class="rounded" aria-label="Tout sélectionner"></th>
                    <th class="px-4 py-3 text-start">Réf.</th>
                    <th class="px-4 py-3 text-start">Client</th>
                    <th class="px-4 py-3 text-start">Wilaya / Livraison</th>
                    <th class="px-4 py-3 text-start">Total</th>
                    <th class="px-4 py-3 text-start">Livreur / Suivi</th>
                    <th class="px-4 py-3 text-start">Statut</th>
                    <th class="px-4 py-3 text-start">Date</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($orders as $o)
                    <tr class="hover:bg-slate-50">
                        <td class="px-3 py-3"><input type="checkbox" class="rounded" data-order="{{ $o->id }}" aria-label="Sélectionner {{ $o->reference }}"></td>
                        <td class="px-4 py-3">
                            <a href="{{ route('admin.orders.show', $o) }}" class="font-semibold text-brand-700">{{ $o->reference }}</a>
                            @if ($o->has_open_stock_issue)
                                <span class="badge ms-1 bg-red-50 text-red-700" title="Stock insuffisant — à vérifier avec le client">⚠️ stock</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">{{ $o->customer_name }}<div class="text-xs text-slate-400">{{ $o->phone }}</div></td>
                        <td class="px-4 py-3">{{ optional($o->wilaya)->name }}<div class="text-xs text-slate-400">{{ $o->delivery_type === 'home' ? 'À domicile' : 'Stop desk' }}</div></td>
                        <td class="px-4 py-3 font-semibold">@money($o->total)</td>
                        <td class="px-4 py-3">
                            {{ $o->delivery_provider ? ucfirst($o->delivery_provider) : '—' }}
                            @if ($o->tracking_number)<div class="text-xs text-slate-400">{{ $o->tracking_number }}</div>@endif
                            @if ($o->noest_status)<div class="text-xs text-cyan-700">{{ $o->noest_status }}</div>@endif
                        </td>
                        <td class="px-4 py-3"><span class="badge bg-{{ $o->status_color }}-50 text-{{ $o->status_color }}-700">{{ $o->status_label }}</span></td>
                        <td class="px-4 py-3 text-slate-400">{{ $o->created_at->format('d/m H:i') }}</td>
                        <td class="px-4 py-3 whitespace-nowrap text-end">
                            <a href="{{ route('admin.orders.print.one', $o) }}" target="_blank" title="Fiche de préparation (A4)">🖨️</a>
                            @if ($o->delivery_provider === 'noest' && $o->tracking_number)
                                <a href="{{ route('admin.orders.noest.label', $o) }}" target="_blank" class="ms-1" title="Étiquette Noest">🏷️</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="px-4 py-12 text-center text-slate-400">Aucune commande.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-4">{{ $orders->links() }}</div>
@endsection

@push('scripts')
<script>
(function () {
    const bar   = document.getElementById('bulkBar');
    const count = document.getElementById('bulkCount');
    const print = document.getElementById('bulkPrint');
    const all   = document.getElementById('checkAll');
    const boxes = () => [...document.querySelectorAll('[data-order]')];
    const picked = () => boxes().filter((b) => b.checked).map((b) => b.dataset.order);

    function sync() {
        const ids = picked();
        count.textContent = ids.length;
        bar.classList.toggle('hidden', ids.length === 0);
        bar.classList.toggle('flex', ids.length > 0);
        document.querySelectorAll('[data-bulk-form] [data-ids]').forEach((i) => { i.value = ids.join(','); });
        print.href = '{{ route('admin.orders.print') }}?ids=' + ids.join(',')
            + (document.getElementById('markPreparing').checked ? '&mark_preparing=1' : '');
        all.checked = ids.length > 0 && ids.length === boxes().length;
    }

    document.addEventListener('change', (e) => {
        if (e.target === all) boxes().forEach((b) => { b.checked = all.checked; });
        if (e.target === all || e.target === document.getElementById('markPreparing') || e.target.matches('[data-order]')) sync();
    });

    document.getElementById('bulkClear').addEventListener('click', (e) => {
        e.preventDefault();
        boxes().forEach((b) => { b.checked = false; });
        sync();
    });

    sync();
})();
</script>
@endpush
