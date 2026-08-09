@extends('admin.layout')
@section('title', 'Produits')
@section('heading', 'Produits')

@php
    // Filters currently applied, with the URL that drops each one.
    $drop = function (array $keys) {
        $q = request()->query();
        foreach ([...$keys, 'page'] as $k) { unset($q[$k]); }
        return route('admin.products.index') . ($q ? '?' . http_build_query($q) : '');
    };
    $statuses = ['active' => '✅ Actifs', 'hidden' => '🚫 Masqués'];
    $stocks   = ['in' => '📦 En stock', 'low' => '⚠️ Stock bas', 'out' => '❌ Rupture', 'untracked' => '∞ Non suivi'];
    $flags    = ['promo' => '🏷️ En promo', 'featured' => '⭐ En vedette', 'new' => '🆕 Nouveau', 'shipping' => '🚚 Livraison offerte', 'no_image' => '🖼️ Sans photo'];
    $sorts    = ['' => 'Plus récents', 'oldest' => 'Plus anciens', 'name' => 'Nom (A → Z)', 'price_asc' => 'Prix croissant', 'price_desc' => 'Prix décroissant', 'stock_asc' => 'Stock croissant', 'stock_desc' => 'Stock décroissant', 'views' => 'Les plus vus'];

    $chips = [];
    if (request('q')) $chips[] = ['« ' . request('q') . ' »', $drop(['q'])];
    if (request('category') !== null && request('category') !== '') {
        $cat = request('category') === '0' ? null : $categories->firstWhere('id', (int) request('category'));
        $chips[] = [$cat?->name_fr ?? 'Sans catégorie', $drop(['category'])];
    }
    if (request('brand')) $chips[] = ['Marque : ' . request('brand'), $drop(['brand'])];
    if (isset($statuses[request('status')])) $chips[] = [$statuses[request('status')], $drop(['status'])];
    if (isset($stocks[request('stock')])) $chips[] = [$stocks[request('stock')], $drop(['stock'])];
    foreach ($flags as $key => $label) {
        if (request()->boolean($key)) $chips[] = [$label, $drop([$key])];
    }
    if (request()->filled('min') || request()->filled('max')) {
        $chips[] = [(request('min') ?: '0') . ' – ' . (request('max') ?: '∞') . ' DA', $drop(['min', 'max'])];
    }
@endphp

@section('content')
<div class="mb-4 flex flex-wrap items-center justify-between gap-2">
    <div class="flex flex-wrap items-center gap-2 text-sm">
        <span class="font-semibold">{{ $counts['total'] }}</span>
        <span class="text-slate-500">produit(s)</span>
        @if ($counts['out'])
            <a href="{{ request()->fullUrlWithQuery(['stock' => 'out', 'page' => null]) }}" class="badge bg-red-50 text-red-700 hover:bg-red-100">{{ $counts['out'] }} en rupture</a>
        @endif
        @if ($counts['low'])
            <a href="{{ request()->fullUrlWithQuery(['stock' => 'low', 'page' => null]) }}" class="badge bg-amber-50 text-amber-700 hover:bg-amber-100">{{ $counts['low'] }} stock bas</a>
        @endif
        @if ($counts['hidden'])
            <a href="{{ request()->fullUrlWithQuery(['status' => 'hidden', 'page' => null]) }}" class="badge bg-slate-100 text-slate-600 hover:bg-slate-200">{{ $counts['hidden'] }} masqué(s)</a>
        @endif
    </div>
    <div class="flex gap-2">
        <a href="{{ route('admin.products.import.form') }}" class="btn-ghost">📥 Importer Excel</a>
        <a href="{{ route('admin.products.create') }}" class="btn-primary">+ Nouveau produit</a>
    </div>
</div>

{{-- ── Filter toolbar ──────────────────────────────────────────────────── --}}
<form method="get" class="card mb-4 p-4">
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="lg:col-span-2">
            <label class="label">Recherche</label>
            <input name="q" value="{{ request('q') }}" placeholder="Nom, référence, marque…" class="input">
        </div>
        <div>
            <label class="label">Catégorie</label>
            <select name="category" class="input">
                <option value="">Toutes</option>
                <option value="0" @selected(request('category') === '0')>Sans catégorie</option>
                @foreach ($categories as $cat)
                    <option value="{{ $cat->id }}" @selected((string) request('category') === (string) $cat->id)>{{ $cat->name_fr }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label">Marque</label>
            <select name="brand" class="input">
                <option value="">Toutes</option>
                @foreach ($brands as $b)
                    <option value="{{ $b }}" @selected(request('brand') === $b)>{{ $b }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label">État</label>
            <select name="status" class="input">
                <option value="">Tous</option>
                @foreach ($statuses as $val => $lbl)
                    <option value="{{ $val }}" @selected(request('status') === $val)>{{ $lbl }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label">Stock</label>
            <select name="stock" class="input">
                <option value="">Tous</option>
                @foreach ($stocks as $val => $lbl)
                    <option value="{{ $val }}" @selected(request('stock') === $val)>{{ $lbl }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label">Prix (DA)</label>
            <div class="flex items-center gap-2">
                <input type="number" name="min" value="{{ request('min') }}" placeholder="Min" class="input">
                <span class="text-slate-400">—</span>
                <input type="number" name="max" value="{{ request('max') }}" placeholder="Max" class="input">
            </div>
        </div>
        <div>
            <label class="label">Trier par</label>
            <select name="sort" class="input">
                @foreach ($sorts as $val => $lbl)
                    <option value="{{ $val }}" @selected((string) request('sort') === (string) $val)>{{ $lbl }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm">
        @foreach ($flags as $key => $label)
            <label class="flex cursor-pointer items-center gap-1.5 text-slate-600">
                <input type="checkbox" name="{{ $key }}" value="1" @checked(request()->boolean($key))
                       class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                {{ $label }}
            </label>
        @endforeach
        <label class="ms-auto flex items-center gap-1.5 text-slate-500">
            Par page
            <select name="per_page" class="input w-auto py-1">
                @foreach ([20, 50, 100] as $n)
                    <option value="{{ $n }}" @selected((int) request('per_page', 20) === $n)>{{ $n }}</option>
                @endforeach
            </select>
        </label>
        <button class="btn-primary">Filtrer</button>
        @if ($chips)
            <a href="{{ route('admin.products.index') }}" class="btn-ghost">Réinitialiser</a>
        @endif
    </div>
</form>

@if ($chips)
    <div class="mb-4 flex flex-wrap items-center gap-2">
        @foreach ($chips as [$label, $url])
            <a href="{{ $url }}" class="group inline-flex items-center gap-1.5 rounded-full bg-brand-50 py-1 pe-2 ps-3 text-xs font-medium text-brand-700 ring-1 ring-brand-100 hover:bg-brand-100">
                {{ $label }}
                <span class="grid h-4 w-4 place-items-center rounded-full bg-brand-600/10 group-hover:bg-brand-600 group-hover:text-white">×</span>
            </a>
        @endforeach
    </div>
@endif

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
                                    <p class="text-xs text-slate-400">
                                        {{ $p->sku }}@if ($p->brand) · {{ $p->brand }}@endif
                                    </p>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3">{{ optional($p->category)->name_fr ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <span class="font-semibold">@money($p->price)</span>
                            @if ($p->on_sale)
                                <span class="ms-1 text-xs text-slate-400 line-through">@money($p->compare_at_price)</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if (! $p->track_stock)
                                <span class="text-slate-400">∞</span>
                            @elseif ($p->stock <= 0)
                                <span class="badge bg-red-50 text-red-700">Rupture</span>
                            @elseif ($p->stock <= \App\Http\Controllers\Admin\ProductController::LOW_STOCK)
                                <span class="badge bg-amber-50 text-amber-700">{{ $p->stock }}</span>
                            @else
                                {{ $p->stock }}
                            @endif
                        </td>
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
                    <tr><td colspan="6" class="px-4 py-12 text-center text-slate-400">
                        @if ($chips)
                            Aucun produit ne correspond à ces filtres.
                            <a href="{{ route('admin.products.index') }}" class="text-brand-700">Réinitialiser</a>.
                        @else
                            Aucun produit. <a href="{{ route('admin.products.create') }}" class="text-brand-700">Ajouter le premier</a>.
                        @endif
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-4">{{ $products->links() }}</div>
@endsection
