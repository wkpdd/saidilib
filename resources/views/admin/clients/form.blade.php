@extends('admin.layout')
@section('title', $client->exists ? 'Modifier client' : 'Nouveau client')
@section('heading', $client->exists ? 'Modifier client' : 'Nouveau client')

@section('content')
<form action="{{ $client->exists ? route('admin.clients.update', $client) : route('admin.clients.store') }}"
      method="post" class="mx-auto max-w-2xl card p-6">
    @csrf
    @if ($client->exists) @method('PUT') @endif

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label class="label">Nom complet</label>
            <input name="name" value="{{ old('name', $client->name) }}" required class="input">
        </div>
        <div>
            <label class="label">Type</label>
            <select name="type" class="input">
                @foreach (\App\Models\Client::TYPES as $val => $lbl)
                    <option value="{{ $val }}" @selected(old('type', $client->type)===$val)>{{ $lbl }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label">Téléphone</label>
            <input name="phone" value="{{ old('phone', $client->phone) }}" class="input">
        </div>
        <div>
            <label class="label">Email (optionnel — pour la connexion)</label>
            <input name="email" type="email" value="{{ old('email', $client->email) }}" class="input">
        </div>
        <div>
            <label class="label">Wilaya</label>
            <select name="wilaya_id" class="input">
                <option value="">—</option>
                @foreach ($wilayas as $w)
                    <option value="{{ $w->id }}" @selected(old('wilaya_id', $client->wilaya_id)==$w->id)>{{ $w->label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label">Commune</label>
            <input name="commune" value="{{ old('commune', $client->commune) }}" class="input">
        </div>
        <div class="sm:col-span-2">
            <label class="label">Adresse</label>
            <input name="address" value="{{ old('address', $client->address) }}" class="input">
        </div>
        <div>
            <label class="label">Limite de crédit (DA)</label>
            <input name="credit_limit" type="number" step="0.01" min="0" value="{{ old('credit_limit', $client->credit_limit ?? 0) }}" class="input">
            <p class="mt-1 text-xs text-slate-400">Solde autorisé avant alerte. 0 = aucun crédit.</p>
        </div>
        <div>
            <label class="label">Mot de passe {{ $client->exists ? '(laisser vide pour garder)' : '(optionnel)' }}</label>
            <input name="password" type="password" class="input" autocomplete="new-password">
        </div>
        <div class="sm:col-span-2">
            <label class="label">Notes</label>
            <textarea name="notes" rows="2" class="input">{{ old('notes', $client->notes) }}</textarea>
        </div>
    </div>

    <label class="mt-4 flex items-center gap-2 text-sm">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $client->is_active ?? true)) class="rounded"> Compte actif
    </label>

    <div class="mt-6 flex gap-2">
        <button class="btn-primary">💾 Enregistrer</button>
        <a href="{{ route('admin.clients.index') }}" class="btn-ghost">Annuler</a>
        @if ($client->exists)
            <form action="{{ route('admin.clients.destroy', $client) }}" method="post" class="ms-auto" onsubmit="return confirm('Supprimer ce client et son historique ?')">
                @csrf @method('DELETE')
                <button class="btn bg-red-50 text-red-700 hover:bg-red-100">Supprimer</button>
            </form>
        @endif
    </div>
</form>
@endsection
