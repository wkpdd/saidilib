@extends('admin.layout')
@section('title', 'Fournisseurs')
@section('heading', 'Fournisseurs')

@section('content')
<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
    <form method="get" class="flex gap-2">
        <input name="q" value="{{ request('q') }}" placeholder="Nom, contact, téléphone…" class="input w-64">
        <button class="btn-ghost">Filtrer</button>
    </form>
    <a href="{{ route('admin.suppliers.create') }}" class="btn-primary">+ Nouveau fournisseur</a>
</div>

<div class="card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3 text-start">Fournisseur</th>
                    <th class="px-4 py-3 text-start">Contact</th>
                    <th class="px-4 py-3 text-start">Téléphone</th>
                    <th class="px-4 py-3 text-end">Réceptions</th>
                    <th class="px-4 py-3 text-start">État</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($suppliers as $s)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 font-medium">{{ $s->name }}<p class="text-xs text-slate-400">{{ $s->email ?: '—' }}</p></td>
                        <td class="px-4 py-3">{{ $s->contact_name ?: '—' }}</td>
                        <td class="px-4 py-3">{{ $s->phone ?: '—' }}</td>
                        <td class="px-4 py-3 text-end">{{ $s->receipts_count }}</td>
                        <td class="px-4 py-3"><span class="badge {{ $s->is_active ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700' }}">{{ $s->is_active ? 'Actif' : 'Inactif' }}</span></td>
                        <td class="px-4 py-3 text-end"><a href="{{ route('admin.suppliers.edit', $s) }}" class="text-brand-700 hover:underline">Modifier</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-10 text-center text-slate-400">Aucun fournisseur.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-4">{{ $suppliers->links() }}</div>
@endsection
