@extends('admin.layout')
@section('title', $user->exists ? 'Modifier membre' : 'Nouveau membre')
@section('heading', $user->exists ? 'Modifier : ' . $user->name : 'Nouveau membre')

@section('content')
<form action="{{ $user->exists ? route('admin.users.update', $user) : route('admin.users.store') }}"
      method="post" class="mx-auto max-w-2xl card p-6">
    @csrf
    @if ($user->exists) @method('PUT') @endif

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label class="label">Nom complet *</label>
            <input name="name" value="{{ old('name', $user->name) }}" required class="input">
        </div>
        <div>
            <label class="label">Email *</label>
            <input name="email" type="email" value="{{ old('email', $user->email) }}" required class="input">
        </div>
        <div>
            <label class="label">Téléphone</label>
            <input name="phone" value="{{ old('phone', $user->phone) }}" class="input">
        </div>
        <div>
            <label class="label">Rôle *</label>
            <select name="role" class="input">
                @foreach (\App\Models\User::ROLES as $value => $lbl)
                    <option value="{{ $value }}" @selected(old('role', $user->role)===$value)>{{ $lbl }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label">Mot de passe {{ $user->exists ? '(laisser vide pour ne pas changer)' : '*' }}</label>
            <input name="password" type="password" {{ $user->exists ? '' : 'required' }} class="input" autocomplete="new-password">
        </div>
        <div class="flex items-end">
            <label class="flex items-center gap-2 text-sm">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active ?? true)) class="rounded"> Compte actif
            </label>
        </div>
    </div>

    <div class="mt-4 rounded-xl bg-slate-50 p-4 text-xs text-slate-500">
        <b>Rôles :</b> Administrateur = accès complet (équipe + paramètres) · Manager / Employé = commandes, produits, catégories, pixels et livraison.
    </div>

    <div class="mt-6 flex gap-2">
        <button class="btn-primary">💾 Enregistrer</button>
        <a href="{{ route('admin.users.index') }}" class="btn-ghost">Annuler</a>
    </div>
</form>
@endsection
