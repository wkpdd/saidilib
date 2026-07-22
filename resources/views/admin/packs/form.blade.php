@extends('admin.layout')
@section('title', $pack->exists ? 'Modifier le pack' : 'Nouveau pack')
@section('heading', $pack->exists ? 'Modifier : ' . $pack->name_fr : '🎒 Nouveau pack scolaire')

@section('content')
<form action="{{ $pack->exists ? route('admin.packs.update', $pack) : route('admin.packs.store') }}"
      method="post" enctype="multipart/form-data" class="grid gap-6 lg:grid-cols-3">
    @csrf
    @if ($pack->exists) @method('PUT') @endif

    <div class="space-y-6 lg:col-span-2">
        <div class="card p-5">
            <h2 class="mb-4 font-semibold">Informations</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label">Nom (Français) *</label>
                    <input name="name_fr" value="{{ old('name_fr', $pack->name_fr) }}" required class="input" placeholder="Pack 1ère année moyenne">
                </div>
                <div>
                    <label class="label">Nom (Arabe)</label>
                    <input name="name_ar" value="{{ old('name_ar', $pack->name_ar) }}" dir="rtl" class="input" placeholder="حزمة السنة الأولى متوسط">
                </div>
                <div class="sm:col-span-2">
                    <label class="label">Description (FR)</label>
                    <textarea name="description_fr" rows="2" class="input">{{ old('description_fr', $pack->description_fr) }}</textarea>
                </div>
                <div class="sm:col-span-2">
                    <label class="label">Description (AR)</label>
                    <textarea name="description_ar" rows="2" dir="rtl" class="input">{{ old('description_ar', $pack->description_ar) }}</textarea>
                </div>
            </div>
        </div>

        <div class="card p-5">
            <div class="mb-1 flex items-center justify-between">
                <h2 class="font-semibold">Articles du pack</h2>
                <button type="button" onclick="addPackItem()" class="text-sm font-semibold text-brand-700">+ Ajouter un article</button>
            </div>
            <p class="mb-3 text-xs text-slate-400">La liste unifiée des fournitures. La quantité par élève pour chaque article.</p>
            <div class="mb-1 hidden grid-cols-12 gap-2 px-1 text-[11px] font-semibold uppercase text-slate-400 sm:grid">
                <span class="col-span-8">Produit</span><span class="col-span-2">Qté</span><span class="col-span-2"></span>
            </div>
            <div id="packItems" class="space-y-2">
                @foreach (old('items', $pack->items->map(fn ($i) => ['product_id' => $i->product_id, 'variant_id' => $i->product_variant_id, 'quantity' => $i->quantity])->toArray()) as $i => $item)
                    <div class="grid grid-cols-12 items-center gap-2" data-pack-item>
                        <select name="items[{{ $i }}][product_id]" required class="input col-span-8">
                            @foreach ($products as $p)
                                <option value="{{ $p->id }}" @selected(($item['product_id'] ?? null) == $p->id)>{{ $p->name_fr }} {{ $p->sku }} — {{ number_format((float) $p->price, 0, ',', ' ') }} DA</option>
                            @endforeach
                        </select>
                        <input type="number" name="items[{{ $i }}][quantity]" value="{{ $item['quantity'] ?? 1 }}" min="1" class="input col-span-2">
                        <button type="button" class="col-span-2 text-red-500" onclick="this.closest('[data-pack-item]').remove()">✕</button>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="space-y-6">
        <div class="card p-5">
            <h2 class="mb-4 font-semibold">Publication</h2>
            <label class="mb-3 flex items-center gap-2">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $pack->is_active)) class="rounded">
                <span class="text-sm">Pack visible (dans la section Packs)</span>
            </label>
            <label class="label">Prix promo du pack (DA, optionnel)</label>
            <input name="price" type="number" step="any" min="0" value="{{ old('price', $pack->price ? (float) $pack->price : '') }}" class="input" placeholder="vide = somme des articles">
            @if ($pack->exists)
                <p class="mt-2 text-xs text-slate-500">Somme actuelle des articles : <b>{{ number_format($pack->items_total, 0, ',', ' ') }} DA</b></p>
            @endif
            <p class="mt-1 text-xs text-slate-400">S'il est inférieur à la somme, le client paie ce prix (remise répartie sur les lignes).</p>
        </div>

        <div class="card p-5">
            <h2 class="mb-4 font-semibold">Photo du pack</h2>
            @if ($pack->image_url)
                <img src="{{ $pack->image_url }}" class="mb-3 aspect-video w-full rounded-xl object-cover">
            @endif
            <input type="file" name="image" accept="image/*" class="input">
            <p class="mt-1 text-xs text-slate-400">Compressée automatiquement.</p>
        </div>

        <button class="btn-primary w-full py-3">💾 Enregistrer le pack</button>
        @if (session('success'))<p class="text-center text-sm text-green-600">{{ session('success') }}</p>@endif
    </div>
</form>
@endsection

@push('scripts')
<script>
    let packIdx = {{ count(old('items', $pack->items->toArray() ?: [])) }};
    @php
        // Computed here because @json's argument parser trips on quoted commas.
        $packProductsJs = $products->map(fn ($p) => [
            'id' => $p->id,
            'label' => $p->name_fr . ' ' . ($p->sku ?? '') . ' — ' . number_format((float) $p->price, 0, ',', ' ') . ' DA',
        ])->values();
    @endphp
    const packProducts = @json($packProductsJs);
    function addPackItem() {
        const row = document.createElement('div');
        row.className = 'grid grid-cols-12 items-center gap-2';
        row.setAttribute('data-pack-item', '');
        row.innerHTML = `
            <select name="items[${packIdx}][product_id]" required class="input col-span-8">
                ${packProducts.map((p) => `<option value="${p.id}">${p.label}</option>`).join('')}
            </select>
            <input type="number" name="items[${packIdx}][quantity]" value="1" min="1" class="input col-span-2">
            <button type="button" class="col-span-2 text-red-500" onclick="this.closest('[data-pack-item]').remove()">✕</button>`;
        document.getElementById('packItems').appendChild(row);
        packIdx++;
    }
</script>
@endpush
