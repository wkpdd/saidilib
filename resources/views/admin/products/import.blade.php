@extends('admin.layout')
@section('title', 'Importer des produits')
@section('heading', 'Importer des produits (Excel)')

@section('content')
<div class="mx-auto max-w-2xl space-y-6">
    <a href="{{ route('admin.products.index') }}" class="text-sm text-slate-500 hover:underline">← Produits</a>

    {{-- Step 1: download template --}}
    <div class="card p-6">
        <h2 class="font-semibold">1. Téléchargez le modèle</h2>
        <p class="mt-1 text-sm text-slate-500">Un fichier Excel avec les bonnes colonnes et un exemple. Remplissez une ligne par produit.</p>
        <a href="{{ route('admin.products.import.template') }}" class="btn-primary mt-4">⬇️ Télécharger le modèle Excel (.xlsx)</a>
    </div>

    {{-- Step 2: upload --}}
    <div class="card p-6">
        <h2 class="font-semibold">2. Importez votre fichier rempli</h2>
        <p class="mt-1 text-sm text-slate-500">Formats acceptés : .xlsx, .xls, .csv (max 10 Mo).</p>
        <form action="{{ route('admin.products.import') }}" method="post" enctype="multipart/form-data" class="mt-4 flex flex-wrap items-center gap-3">
            @csrf
            <input type="file" name="file" accept=".xlsx,.xls,.csv" required class="input max-w-sm">
            <button class="btn-primary">📤 Importer</button>
        </form>

        @if (session('import_errors') && count(session('import_errors')))
            <div class="mt-4 rounded-xl bg-amber-50 p-4 text-sm text-amber-800 ring-1 ring-amber-200">
                <p class="mb-1 font-semibold">Lignes ignorées :</p>
                <ul class="list-disc ps-5">
                    @foreach (session('import_errors') as $e)<li>{{ $e }}</li>@endforeach
                </ul>
            </div>
        @endif
    </div>

    {{-- Column guide --}}
    <div class="card p-6 text-sm">
        <h2 class="mb-2 font-semibold">Colonnes</h2>
        <ul class="grid gap-1 text-slate-600 sm:grid-cols-2">
            <li><b>name_fr</b> — nom du produit <span class="text-red-500">(obligatoire)</span></li>
            <li><b>prix</b> — prix en DA <span class="text-red-500">(obligatoire)</span></li>
            <li><b>categorie</b> — nom (créée si absente)</li>
            <li><b>sku</b> — référence (met à jour si déjà présent)</li>
            <li><b>ancien_prix</b> — prix barré</li>
            <li><b>stock</b> — quantité (nombre entier)</li>
            <li><b>marque</b>, <b>name_ar</b>, <b>description</b></li>
            <li><b>actif / nouveau / vedette</b> — 1 ou 0</li>
            <li><b>image_url</b> — lien direct vers une photo</li>
        </ul>
        <p class="mt-3 text-xs text-slate-400">Astuce : si vous renseignez un <b>sku</b> déjà existant, le produit est mis à jour au lieu d'être dupliqué — pratique pour corriger les prix/stock en masse.</p>
    </div>
</div>
@endsection
