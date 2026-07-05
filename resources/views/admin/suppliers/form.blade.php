@extends('admin.layout')
@section('title', $supplier->exists ? 'Modifier fournisseur' : 'Nouveau fournisseur')
@section('heading', $supplier->exists ? 'Modifier : ' . $supplier->name : 'Nouveau fournisseur')

@section('content')
<form action="{{ $supplier->exists ? route('admin.suppliers.update', $supplier) : route('admin.suppliers.store') }}"
      method="post" class="mx-auto max-w-2xl card p-6">
    @csrf
    @if ($supplier->exists) @method('PUT') @endif

    <div class="grid gap-4 sm:grid-cols-2">
        <div class="sm:col-span-2">
            <label class="label">Nom / Raison sociale *</label>
            <input name="name" value="{{ old('name', $supplier->name) }}" required class="input">
        </div>
        <div>
            <label class="label">Personne de contact</label>
            <input name="contact_name" value="{{ old('contact_name', $supplier->contact_name) }}" class="input">
        </div>
        <div>
            <label class="label">Téléphone</label>
            <input name="phone" value="{{ old('phone', $supplier->phone) }}" class="input">
        </div>
        <div>
            <label class="label">Email</label>
            <input name="email" type="email" value="{{ old('email', $supplier->email) }}" class="input">
        </div>
        <div>
            <label class="label">RC (Registre de commerce)</label>
            <input name="rc" value="{{ old('rc', $supplier->rc) }}" class="input">
        </div>
        <div>
            <label class="label">NIF</label>
            <input name="nif" value="{{ old('nif', $supplier->nif) }}" class="input">
        </div>
        <div class="sm:col-span-2">
            <label class="label">Adresse</label>
            <input name="address" value="{{ old('address', $supplier->address) }}" class="input">
        </div>
        <div class="sm:col-span-2">
            <label class="label">Notes</label>
            <textarea name="notes" rows="2" class="input">{{ old('notes', $supplier->notes) }}</textarea>
        </div>
    </div>

    <label class="mt-4 flex items-center gap-2 text-sm">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $supplier->is_active ?? true)) class="rounded"> Fournisseur actif
    </label>

    <div class="mt-6 flex gap-2">
        <button class="btn-primary">💾 Enregistrer</button>
        <a href="{{ route('admin.suppliers.index') }}" class="btn-ghost">Annuler</a>
        @if ($supplier->exists)
            <form action="{{ route('admin.suppliers.destroy', $supplier) }}" method="post" class="ms-auto" onsubmit="return confirm('Supprimer ce fournisseur ?')">
                @csrf @method('DELETE')
                <button class="btn bg-red-50 text-red-700 hover:bg-red-100">Supprimer</button>
            </form>
        @endif
    </div>
</form>
@endsection
