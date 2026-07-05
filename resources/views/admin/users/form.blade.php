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
            <select name="role" id="roleSelect" class="input">
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

    {{-- Permissions (RBAC) --}}
    @php
        $granted = old('permissions', $user->exists ? $user->grantedPermissions() : (\App\Models\User::ROLE_PRESETS['staff'] ?? []));
    @endphp
    <div id="permBlock" class="mt-5 rounded-xl border border-slate-200 p-4">
        <div class="mb-2 flex items-center justify-between">
            <label class="text-sm font-semibold text-ink-700">Permissions — sections accessibles</label>
            <span id="adminAllNote" class="hidden text-xs font-medium text-brand-700">Administrateur : accès complet à tout ✓</span>
        </div>
        <div id="permGrid" class="grid grid-cols-2 gap-2 sm:grid-cols-3">
            @foreach (\App\Models\User::PERMISSIONS as $key => $label)
                <label class="flex items-center gap-2 rounded-lg bg-slate-50 px-3 py-2 text-sm">
                    <input type="checkbox" name="permissions[]" value="{{ $key }}"
                           @checked(in_array($key, (array) $granted, true)) class="perm-box rounded">
                    {{ $label }}
                </label>
            @endforeach
        </div>
        <p class="mt-2 text-xs text-slate-400">Choisir un rôle pré-remplit les permissions ; vous pouvez ensuite les ajuster une par une.</p>
    </div>

    <div class="mt-6 flex gap-2">
        <button class="btn-primary">💾 Enregistrer</button>
        <a href="{{ route('admin.users.index') }}" class="btn-ghost">Annuler</a>
    </div>
</form>

@push('scripts')
<script>
    (function () {
        const presets = @json(\App\Models\User::ROLE_PRESETS);
        const role = document.getElementById('roleSelect');
        const grid = document.getElementById('permGrid');
        const note = document.getElementById('adminAllNote');
        const boxes = () => grid.querySelectorAll('.perm-box');

        function applyRole(fillFromPreset) {
            const isAdmin = role.value === 'admin';
            note.classList.toggle('hidden', !isAdmin);
            grid.classList.toggle('opacity-50', isAdmin);
            boxes().forEach((b) => {
                b.disabled = isAdmin;                 // admin = all, locked
                if (isAdmin) b.checked = true;
            });
            if (!isAdmin && fillFromPreset) {
                const allow = presets[role.value] || [];
                boxes().forEach((b) => { b.checked = allow.includes(b.value); });
            }
        }

        role.addEventListener('change', () => applyRole(true));
        applyRole(false); // initial: honour saved/old values, just lock if admin
    })();
</script>
@endpush
@endsection
