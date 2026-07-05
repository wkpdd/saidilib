@extends('admin.layout')
@section('title', $receipt->exists ? 'Modifier bon' : 'Nouveau bon de réception')
@section('heading', $receipt->exists ? 'Bon ' . $receipt->reference : 'Nouveau bon de réception')

@section('content')
<form action="{{ $receipt->exists ? route('admin.receipts.update', $receipt) : route('admin.receipts.store') }}"
      method="post" enctype="multipart/form-data" class="grid gap-6 lg:grid-cols-3">
    @csrf
    @if ($receipt->exists) @method('PUT') @endif

    <div class="space-y-6 lg:col-span-2">
        {{-- Line items --}}
        <div class="card p-5">
            <div class="mb-1 flex items-center justify-between">
                <h2 class="font-semibold">Articles reçus</h2>
                <button type="button" onclick="addItem()" class="text-sm font-semibold text-brand-700">+ Ajouter une ligne</button>
            </div>
            <p class="mb-3 text-xs text-slate-400">Choisissez un produit du catalogue pour que sa quantité soit ajoutée au stock à la réception. Lot &amp; péremption sont optionnels.</p>
            <div class="mb-1 hidden grid-cols-12 gap-2 px-1 text-[11px] font-semibold uppercase text-slate-400 sm:grid">
                <span class="col-span-4">Produit</span><span class="col-span-2">Lot</span><span class="col-span-2">Péremption</span><span class="col-span-1">Qté</span><span class="col-span-2">Coût U.</span><span class="col-span-1"></span>
            </div>
            <div id="items" class="space-y-2">
                @foreach (old('items', $receipt->exists ? $receipt->items->toArray() : []) as $i => $it)
                    <div class="grid grid-cols-12 items-center gap-2" data-item-row>
                        <select name="items[{{ $i }}][product_id]" class="input col-span-4 text-sm">
                            <option value="">— Produit —</option>
                            @foreach ($products as $p)
                                <option value="{{ $p->id }}" @selected(($it['product_id'] ?? null) == $p->id)>{{ $p->name_fr }}</option>
                            @endforeach
                        </select>
                        <input name="items[{{ $i }}][lot_number]" value="{{ $it['lot_number'] ?? '' }}" placeholder="Lot" class="input col-span-2">
                        <input name="items[{{ $i }}][expiry_date]" value="{{ isset($it['expiry_date']) ? \Illuminate\Support\Str::of($it['expiry_date'])->substr(0,10) : '' }}" type="date" class="input col-span-2 text-xs">
                        <input name="items[{{ $i }}][quantity]" value="{{ $it['quantity'] ?? 1 }}" type="number" min="0" placeholder="Qté" class="input col-span-1">
                        <input name="items[{{ $i }}][unit_cost]" value="{{ $it['unit_cost'] ?? 0 }}" type="number" step="any" min="0" placeholder="Coût" class="input col-span-2">
                        <button type="button" onclick="this.closest('[data-item-row]').remove()" class="col-span-1 text-red-500">✕</button>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Document meta --}}
    <div class="space-y-6">
        <div class="card p-5">
            <h2 class="mb-4 font-semibold">Informations du bon</h2>
            <label class="label">Fournisseur</label>
            <select name="supplier_id" class="input mb-3">
                <option value="">—</option>
                @foreach ($suppliers as $s)
                    <option value="{{ $s->id }}" @selected(old('supplier_id', $receipt->supplier_id)==$s->id)>{{ $s->name }}</option>
                @endforeach
            </select>
            <label class="label">N° facture fournisseur</label>
            <input name="supplier_invoice" value="{{ old('supplier_invoice', $receipt->supplier_invoice) }}" class="input mb-3">
            <label class="label">Date du document</label>
            <input name="document_date" type="date" value="{{ old('document_date', optional($receipt->document_date)->format('Y-m-d')) }}" class="input mb-3">
            <label class="label">Document (facture PDF / photo)</label>
            <input name="document" type="file" accept=".pdf,image/*" class="input mb-2">
            @if ($receipt->exists && $receipt->document_path)
                <a href="{{ route('admin.receipts.document', $receipt) }}" class="text-xs text-brand-700 hover:underline">📎 Document actuel</a>
            @endif
            <label class="label mt-3">Note</label>
            <textarea name="note" rows="2" class="input">{{ old('note', $receipt->note) }}</textarea>
        </div>

        <div class="card p-5">
            <button class="btn-primary w-full">💾 Enregistrer</button>
            <a href="{{ route('admin.receipts.index') }}" class="btn-ghost mt-2 w-full">Annuler</a>
            <p class="mt-2 text-xs text-slate-400">Le stock n'est mis à jour qu'après « Réceptionner » sur la fiche du bon.</p>
        </div>
    </div>
</form>

@push('scripts')
<script>
    const products = @json($products->map(fn ($p) => ['id' => $p->id, 'name' => $p->name_fr])->values());
    let iIndex = {{ count(old('items', $receipt->exists ? $receipt->items->toArray() : [])) }};
    function productOptions() {
        return '<option value="">— Produit —</option>' + products.map((p) => `<option value="${p.id}">${p.name}</option>`).join('');
    }
    function addItem() {
        const row = document.createElement('div');
        row.className = 'grid grid-cols-12 items-center gap-2';
        row.setAttribute('data-item-row', '');
        row.innerHTML = `
            <select name="items[${iIndex}][product_id]" class="input col-span-4 text-sm">${productOptions()}</select>
            <input name="items[${iIndex}][lot_number]" placeholder="Lot" class="input col-span-2">
            <input name="items[${iIndex}][expiry_date]" type="date" class="input col-span-2 text-xs">
            <input name="items[${iIndex}][quantity]" type="number" min="0" value="1" placeholder="Qté" class="input col-span-1">
            <input name="items[${iIndex}][unit_cost]" type="number" step="any" min="0" value="0" placeholder="Coût" class="input col-span-2">
            <button type="button" class="col-span-1 text-red-500">✕</button>`;
        row.querySelector('button').addEventListener('click', () => row.remove());
        document.getElementById('items').appendChild(row);
        iIndex++;
    }
    if (iIndex === 0) addItem();
</script>
@endpush
@endsection
