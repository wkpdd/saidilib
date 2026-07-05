@extends('admin.layout')
@section('title', 'Déclarer un incident')
@section('heading', 'Déclarer une perte / casse')

@section('content')
<form action="{{ route('admin.incidents.store') }}" method="post" class="mx-auto max-w-xl card p-6">
    @csrf

    <label class="label">Article du catalogue</label>
    <select name="product_id" class="input mb-1" onchange="document.getElementById('freeName').classList.toggle('hidden', !!this.value)">
        <option value="">— Article hors catalogue —</option>
        @foreach ($products as $p)
            <option value="{{ $p->id }}" @selected(old('product_id')==$p->id)>{{ $p->name_fr }} @if($p->track_stock)(stock : {{ $p->stock }})@endif</option>
        @endforeach
    </select>

    <div id="freeName" class="{{ old('product_id') ? 'hidden' : '' }} mb-4">
        <label class="label mt-3">Ou nom libre de l'article</label>
        <input name="product_name" value="{{ old('product_name') }}" class="input" placeholder="Ex : Carton de ramettes A4">
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label class="label">Type d'incident</label>
            <select name="type" class="input">
                @foreach (\App\Models\InventoryIncident::TYPES as $val => $lbl)
                    <option value="{{ $val }}" @selected(old('type')===$val)>{{ $lbl }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label">Quantité</label>
            <input name="quantity" type="number" min="1" value="{{ old('quantity', 1) }}" required class="input">
        </div>
        <div>
            <label class="label">Coût estimé (DA)</label>
            <input name="cost_estimate" type="number" step="0.01" min="0" value="{{ old('cost_estimate', 0) }}" class="input">
        </div>
    </div>

    <label class="label mt-4">Motif / détails</label>
    <textarea name="reason" rows="3" class="input">{{ old('reason') }}</textarea>

    <label class="mt-4 flex items-center gap-2 text-sm">
        <input type="hidden" name="adjust_stock" value="0">
        <input type="checkbox" name="adjust_stock" value="1" @checked(old('adjust_stock')) class="rounded">
        Décrémenter le stock de l'article (si suivi de stock activé)
    </label>

    <div class="mt-6 flex gap-2">
        <button class="btn-primary">💾 Enregistrer</button>
        <a href="{{ route('admin.incidents.index') }}" class="btn-ghost">Annuler</a>
    </div>
</form>
@endsection
