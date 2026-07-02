@extends('admin.layout')
@section('title', 'Équipe')
@section('heading', 'Équipe')

@section('content')
<div class="mb-5 grid gap-4 sm:grid-cols-4">
    @php
        $cards = [
            ['Membres', $stats['total'], '👥'],
            ['Administrateurs', $stats['admins'], '🛡️'],
            ['Employés', $stats['staff'], '🧑‍💼'],
            ['Actifs', $stats['active'], '✅'],
        ];
    @endphp
    @foreach ($cards as [$label, $value, $icon])
        <div class="card p-5">
            <div class="flex items-center justify-between">
                <span class="text-sm text-slate-500">{{ $label }}</span><span class="text-2xl">{{ $icon }}</span>
            </div>
            <p class="mt-2 text-2xl font-bold">{{ $value }}</p>
        </div>
    @endforeach
</div>

<div class="mb-4 flex items-center justify-between">
    <p class="text-sm text-slate-500">Administrateurs et employés ayant accès à l'administration.</p>
    <a href="{{ route('admin.users.create') }}" class="btn-primary">+ Ajouter un membre</a>
</div>

<div class="card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3 text-start">Membre</th>
                    <th class="px-4 py-3 text-start">Rôle</th>
                    <th class="px-4 py-3 text-start">Téléphone</th>
                    <th class="px-4 py-3 text-start">État</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($users as $u)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <span class="grid h-9 w-9 place-items-center rounded-full bg-brand-100 font-semibold text-brand-700">{{ strtoupper(substr($u->name, 0, 1)) }}</span>
                                <div>
                                    <p class="font-medium">{{ $u->name }} @if($u->id === auth()->id())<span class="text-xs text-slate-400">(vous)</span>@endif</p>
                                    <p class="text-xs text-slate-400">{{ $u->email }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <span class="badge {{ $u->role==='admin' ? 'bg-brand-50 text-brand-700' : ($u->role==='manager' ? 'bg-indigo-50 text-indigo-700' : 'bg-slate-100 text-slate-600') }}">{{ $u->role_label }}</span>
                        </td>
                        <td class="px-4 py-3">{{ $u->phone ?: '—' }}</td>
                        <td class="px-4 py-3"><span class="badge {{ $u->is_active ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700' }}">{{ $u->is_active ? 'Actif' : 'Désactivé' }}</span></td>
                        <td class="px-4 py-3 text-end">
                            <a href="{{ route('admin.users.edit', $u) }}" class="text-brand-700 hover:underline">Modifier</a>
                            @if ($u->id !== auth()->id())
                                <form action="{{ route('admin.users.destroy', $u) }}" method="post" class="ms-2 inline" onsubmit="return confirm('Supprimer ce membre ?')">
                                    @csrf @method('DELETE')
                                    <button class="text-red-600 hover:underline">Suppr.</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
