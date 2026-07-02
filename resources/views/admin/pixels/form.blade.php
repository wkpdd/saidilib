@extends('admin.layout')
@section('title', $pixel->exists ? 'Modifier pixel' : 'Nouveau pixel')
@section('heading', $pixel->exists ? 'Modifier pixel' : 'Nouveau pixel')

@section('content')
<form action="{{ $pixel->exists ? route('admin.pixels.update', $pixel) : route('admin.pixels.store') }}"
      method="post" class="mx-auto max-w-xl card p-6">
    @csrf
    @if ($pixel->exists) @method('PUT') @endif

    <label class="label">Nom</label>
    <input name="name" value="{{ old('name', $pixel->name) }}" required class="input mb-4" placeholder="Ex : Pixel Facebook principal">

    <label class="label">Plateforme</label>
    <select name="provider" class="input mb-4">
        @foreach (['facebook' => 'Facebook / Meta', 'tiktok' => 'TikTok', 'google' => 'Google (gtag/GA4)', 'snapchat' => 'Snapchat'] as $val => $lbl)
            <option value="{{ $val }}" @selected(old('provider', $pixel->provider)===$val)>{{ $lbl }}</option>
        @endforeach
    </select>

    <label class="label">ID du pixel / mesure</label>
    <input name="pixel_id" value="{{ old('pixel_id', $pixel->pixel_id) }}" required class="input mb-4" placeholder="Ex : 123456789012345 / G-XXXX">

    <label class="label">Access token (CAPI — optionnel)</label>
    <input name="access_token" value="{{ old('access_token', $pixel->access_token) }}" class="input mb-4">

    <div class="space-y-2 text-sm">
        <label class="flex items-center gap-2"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $pixel->is_active ?? true)) class="rounded"> Actif</label>
        <label class="flex items-center gap-2"><input type="hidden" name="is_global" value="0"><input type="checkbox" name="is_global" value="1" @checked(old('is_global', $pixel->is_global ?? true)) class="rounded"> Global (sur toutes les pages)</label>
    </div>
    <p class="mt-2 text-xs text-slate-400">Décochez « Global » pour ne le déclencher que sur les produits sélectionnés (depuis la fiche produit).</p>

    <div class="mt-6 flex gap-2">
        <button class="btn-primary">💾 Enregistrer</button>
        <a href="{{ route('admin.pixels.index') }}" class="btn-ghost">Annuler</a>
    </div>
</form>
@endsection
