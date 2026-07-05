@extends('admin.layout')
@section('title', 'Réceptions de stock')
@section('heading', 'Réceptions de stock (bons de réception)')

@section('content')
<div class="mb-5 grid gap-4 sm:grid-cols-3">
    @php $cards = [['Brouillons', $stats['draft'], '📝'], ['Réceptionnés', $stats['received'], '✅'], ['Valeur reçue', number_format($stats['value'],2,',',' ').' DA', '💰']]; @endphp
    @foreach ($cards as [$label, $value, $icon])
        <div class="card p-5"><div class="flex items-center justify-between"><span class="text-sm text-slate-500">{{ $label }}</span><span class="text-2xl">{{ $icon }}</span></div><p class="mt-2 text-2xl font-bold">{{ $value }}</p></div>
    @endforeach
</div>

<div class="mb-4 flex items-center justify-between">
    <div class="flex gap-2 text-sm">
        <a href="{{ route('admin.receipts.index') }}" class="badge px-3 py-1.5 {{ !request('status') ? 'bg-brand-600 text-white' : 'bg-white ring-1 ring-slate-200' }}">Tous</a>
        @foreach (\App\Models\StockReceipt::STATUS_LABELS as $st => $lbl)
            <a href="{{ route('admin.receipts.index', ['status' => $st]) }}" class="badge px-3 py-1.5 {{ request('status')===$st ? 'bg-brand-600 text-white' : 'bg-white ring-1 ring-slate-200' }}">{{ $lbl }}</a>
        @endforeach
    </div>
    <a href="{{ route('admin.receipts.create') }}" class="btn-primary">+ Nouveau bon</a>
</div>

<div class="card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3 text-start">Référence</th>
                    <th class="px-4 py-3 text-start">Fournisseur</th>
                    <th class="px-4 py-3 text-start">Date</th>
                    <th class="px-4 py-3 text-end">Total</th>
                    <th class="px-4 py-3 text-start">Statut</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($receipts as $r)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3"><a href="{{ route('admin.receipts.show', $r) }}" class="font-medium text-brand-700 hover:underline">{{ $r->reference }}</a></td>
                        <td class="px-4 py-3">{{ optional($r->supplier)->name ?: '—' }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ optional($r->document_date)->format('d/m/Y') ?: $r->created_at->format('d/m/Y') }}</td>
                        <td class="px-4 py-3 text-end font-semibold">@money($r->total_cost)</td>
                        <td class="px-4 py-3">
                            <span class="badge {{ $r->status==='received' ? 'bg-green-50 text-green-700' : ($r->status==='cancelled' ? 'bg-red-50 text-red-700' : 'bg-amber-50 text-amber-700') }}">{{ $r->status_label }}</span>
                        </td>
                        <td class="px-4 py-3 text-end"><a href="{{ route('admin.receipts.show', $r) }}" class="text-brand-700 hover:underline">Ouvrir</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-10 text-center text-slate-400">Aucun bon de réception.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-4">{{ $receipts->links() }}</div>
@endsection
