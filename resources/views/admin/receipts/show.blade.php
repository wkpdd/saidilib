@extends('admin.layout')
@section('title', 'Bon ' . $receipt->reference)
@section('heading', 'Bon de réception ' . $receipt->reference)

@section('content')
<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
    <a href="{{ route('admin.receipts.index') }}" class="text-sm text-slate-500 hover:underline">← Tous les bons</a>
    <div class="flex gap-2">
        @unless ($receipt->is_received)
            <a href="{{ route('admin.receipts.edit', $receipt) }}" class="btn-ghost">Modifier</a>
            <form action="{{ route('admin.receipts.receive', $receipt) }}" method="post"
                  onsubmit="return confirm('Confirmer la réception ? Le stock sera augmenté et le bon verrouillé.')">
                @csrf
                <button class="btn bg-green-600 text-white hover:bg-green-700">📥 Réceptionner (mettre à jour le stock)</button>
            </form>
        @endunless
    </div>
</div>

<div class="grid gap-6 lg:grid-cols-3">
    <div class="space-y-6 lg:col-span-2">
        <div class="card overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-2.5 text-start">Article</th>
                        <th class="px-4 py-2.5 text-start">Lot</th>
                        <th class="px-4 py-2.5 text-start">Péremption</th>
                        <th class="px-4 py-2.5 text-end">Qté</th>
                        <th class="px-4 py-2.5 text-end">Coût U.</th>
                        <th class="px-4 py-2.5 text-end">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($receipt->items as $it)
                        <tr>
                            <td class="px-4 py-2.5">{{ $it->product_name }}@if($it->product)<a href="{{ route('admin.products.edit', $it->product) }}" class="ms-1 text-xs text-brand-700 hover:underline">(fiche)</a>@endif</td>
                            <td class="px-4 py-2.5">{{ $it->lot_number ?: '—' }}</td>
                            <td class="px-4 py-2.5">{{ optional($it->expiry_date)->format('d/m/Y') ?: '—' }}</td>
                            <td class="px-4 py-2.5 text-end font-semibold">{{ $it->quantity }}</td>
                            <td class="px-4 py-2.5 text-end">@money($it->unit_cost)</td>
                            <td class="px-4 py-2.5 text-end">@money($it->line_total)</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="border-t border-slate-100 text-base font-bold">
                    <tr><td colspan="5" class="px-4 py-3 text-end">Total</td><td class="px-4 py-3 text-end text-brand-700">@money($receipt->total_cost)</td></tr>
                </tfoot>
            </table>
        </div>
    </div>

    <div class="space-y-6">
        <div class="card p-5 text-sm">
            <h2 class="mb-3 font-semibold">Détails</h2>
            <div class="space-y-1.5">
                <div class="flex justify-between"><span class="text-slate-400">Statut</span><span class="badge {{ $receipt->is_received ? 'bg-green-50 text-green-700' : 'bg-amber-50 text-amber-700' }}">{{ $receipt->status_label }}</span></div>
                <div class="flex justify-between"><span class="text-slate-400">Fournisseur</span><span>{{ optional($receipt->supplier)->name ?: '—' }}</span></div>
                <div class="flex justify-between"><span class="text-slate-400">Facture</span><span>{{ $receipt->supplier_invoice ?: '—' }}</span></div>
                <div class="flex justify-between"><span class="text-slate-400">Date</span><span>{{ optional($receipt->document_date)->format('d/m/Y') ?: '—' }}</span></div>
                @if ($receipt->received_at)
                    <div class="flex justify-between"><span class="text-slate-400">Reçu le</span><span>{{ $receipt->received_at->format('d/m/Y H:i') }}</span></div>
                @endif
                <div class="flex justify-between"><span class="text-slate-400">Créé par</span><span>{{ optional($receipt->creator)->name ?: '—' }}</span></div>
            </div>
            @if ($receipt->document_path)
                <a href="{{ route('admin.receipts.document', $receipt) }}" class="btn-ghost mt-4 w-full">📎 Télécharger le document</a>
            @endif
            @if ($receipt->note)
                <p class="mt-3 rounded-lg bg-slate-50 p-3 text-xs text-slate-600">{{ $receipt->note }}</p>
            @endif
        </div>

        @if ($receipt->is_received)
            <div class="rounded-xl bg-green-50 p-4 text-sm text-green-800 ring-1 ring-green-200">
                ✅ Ce bon a été réceptionné : le stock des articles a été augmenté.
            </div>
        @endif
    </div>
</div>
@endsection
