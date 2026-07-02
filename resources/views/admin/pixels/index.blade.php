@extends('admin.layout')
@section('title', 'Pixels')
@section('heading', 'Pixels de suivi')

@section('content')
<div class="mb-4 flex items-center justify-between">
    <p class="text-sm text-slate-500">Facebook, TikTok, Google, Snapchat — globaux ou par produit.</p>
    <a href="{{ route('admin.pixels.create') }}" class="btn-primary">+ Nouveau pixel</a>
</div>
<div class="card overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-xs uppercase text-slate-500">
            <tr>
                <th class="px-4 py-3 text-start">Nom</th>
                <th class="px-4 py-3 text-start">Plateforme</th>
                <th class="px-4 py-3 text-start">ID</th>
                <th class="px-4 py-3 text-start">Portée</th>
                <th class="px-4 py-3 text-start">État</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse ($pixels as $px)
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-3 font-medium">{{ $px->name }}</td>
                    <td class="px-4 py-3 capitalize">{{ $px->provider }}</td>
                    <td class="px-4 py-3 font-mono text-xs">{{ $px->pixel_id }}</td>
                    <td class="px-4 py-3">{{ $px->is_global ? 'Tout le site' : $px->products_count . ' produit(s)' }}</td>
                    <td class="px-4 py-3"><span class="badge {{ $px->is_active ? 'bg-green-50 text-green-700' : 'bg-slate-100 text-slate-500' }}">{{ $px->is_active ? 'Actif' : 'Inactif' }}</span></td>
                    <td class="px-4 py-3 text-end">
                        <a href="{{ route('admin.pixels.edit', $px) }}" class="text-brand-700 hover:underline">Modifier</a>
                        <form action="{{ route('admin.pixels.destroy', $px) }}" method="post" class="ms-2 inline" onsubmit="return confirm('Supprimer ?')">
                            @csrf @method('DELETE')
                            <button class="text-red-600 hover:underline">Suppr.</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-4 py-12 text-center text-slate-400">Aucun pixel configuré.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
