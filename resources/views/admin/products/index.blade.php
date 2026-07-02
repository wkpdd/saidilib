@extends('admin.layout')
@section('title', 'Produits')
@section('heading', 'Produits')

@section('content')
<div class="mb-4 flex items-center gap-2">
    <form method="get" class="flex-1">
        <input name="q" value="{{ request('q') }}" placeholder="Rechercher un produit…" class="input max-w-sm">
    </form>
    <a href="{{ route('admin.products.create') }}" class="btn-primary">+ Nouveau produit</a>
</div>

<div class="card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3 text-start">Produit</th>
                    <th class="px-4 py-3 text-start">Catégorie</th>
                    <th class="px-4 py-3 text-start">Prix</th>
                    <th class="px-4 py-3 text-start">Stock</th>
                    <th class="px-4 py-3 text-start">État</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($products as $p)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <img src="{{ $p->main_image_url }}" class="h-11 w-11 rounded-lg object-cover ring-1 ring-slate-100">
                                <div>
                                    <p class="font-medium">{{ $p->name_fr }}</p>
                                    <p class="text-xs text-slate-400">{{ $p->sku }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3">{{ optional($p->category)->name_fr ?? '—' }}</td>
                        <td class="px-4 py-3 font-semibold">@money($p->price)</td>
                        <td class="px-4 py-3">{{ $p->track_stock ? $p->stock : '∞' }}</td>
                        <td class="px-4 py-3">
                            <span class="badge {{ $p->is_active ? 'bg-green-50 text-green-700' : 'bg-slate-100 text-slate-500' }}">{{ $p->is_active ? 'Actif' : 'Masqué' }}</span>
                        </td>
                        <td class="px-4 py-3 text-end">
                            <a href="{{ route('admin.products.edit', $p) }}" class="text-brand-700 hover:underline">Modifier</a>
                            <form action="{{ route('admin.products.destroy', $p) }}" method="post" class="ms-2 inline" onsubmit="return confirm('Supprimer ce produit ?')">
                                @csrf @method('DELETE')
                                <button class="text-red-600 hover:underline">Suppr.</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-12 text-center text-slate-400">Aucun produit. <a href="{{ route('admin.products.create') }}" class="text-brand-700">Ajouter le premier</a>.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-4">{{ $products->links() }}</div>
@endsection
