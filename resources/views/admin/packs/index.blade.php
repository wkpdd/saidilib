@extends('admin.layout')
@section('title', 'Packs scolaires')
@section('heading', '🎒 Packs scolaires')

@section('content')
<div class="mb-6 flex flex-wrap items-center justify-between gap-4">
    <form method="post" action="{{ route('admin.packs.toggle') }}" class="card flex items-center gap-3 p-4">
        @csrf
        <span class="text-sm font-semibold">Section « Packs » sur la page d'accueil :</span>
        @if ($enabled)
            <span class="badge bg-green-50 text-green-700">● Affichée</span>
            <button name="enabled" value="0" class="btn-ghost text-sm">Masquer</button>
        @else
            <span class="badge bg-slate-100 text-slate-500">● Masquée</span>
            <button name="enabled" value="1" class="btn-primary text-sm">Afficher</button>
        @endif
    </form>
    <a href="{{ route('admin.packs.create') }}" class="btn-primary">+ Nouveau pack</a>
</div>

@if (session('success'))<p class="mb-4 text-sm text-green-600">{{ session('success') }}</p>@endif

<div class="card overflow-x-auto p-0">
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b border-slate-200 text-left text-xs uppercase text-slate-400">
                <th class="p-3">Pack</th><th class="p-3">Articles</th><th class="p-3">Prix (somme)</th>
                <th class="p-3">Prix promo</th><th class="p-3">Statut</th><th class="p-3"></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($packs as $pack)
                <tr class="border-b border-slate-50 hover:bg-slate-50/60">
                    <td class="p-3">
                        <div class="flex items-center gap-3">
                            @if ($pack->image_url)<img src="{{ $pack->image_url }}" class="h-10 w-10 rounded-lg object-cover">@endif
                            <div>
                                <p class="font-medium">{{ $pack->name_fr }}</p>
                                @if ($pack->name_ar)<p class="text-xs text-slate-400" dir="rtl">{{ $pack->name_ar }}</p>@endif
                            </div>
                        </div>
                    </td>
                    <td class="p-3">{{ $pack->items_count }}</td>
                    <td class="p-3">{{ number_format($pack->items_total, 0, ',', ' ') }} DA</td>
                    <td class="p-3">{{ $pack->price ? number_format((float) $pack->price, 0, ',', ' ') . ' DA' : '—' }}</td>
                    <td class="p-3">
                        <span class="badge {{ $pack->is_active ? 'bg-green-50 text-green-700' : 'bg-slate-100 text-slate-500' }}">
                            {{ $pack->is_active ? 'Actif' : 'Inactif' }}
                        </span>
                    </td>
                    <td class="p-3 text-right">
                        <a href="{{ route('admin.packs.edit', $pack) }}" class="btn-ghost text-sm">Modifier</a>
                        <form method="post" action="{{ route('admin.packs.destroy', $pack) }}" class="inline"
                              onsubmit="return confirm('Supprimer ce pack ? (les produits ne sont pas touchés)')">
                            @csrf @method('DELETE')
                            <button class="btn-ghost text-sm text-red-600">Suppr.</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="p-6 text-center text-slate-400">Aucun pack — créez le premier (ex. « Pack 1ère année moyenne »).</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<p class="mt-3 text-xs text-slate-400">Le client ajoute tout le pack au panier en un clic. Prix promo (optionnel) : s'il est inférieur à la somme, la remise est répartie sur les lignes de la commande.</p>
@endsection
