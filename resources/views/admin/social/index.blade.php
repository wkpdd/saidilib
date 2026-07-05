@extends('admin.layout')
@section('title', 'Réseaux sociaux')
@section('heading', 'Publier sur les réseaux sociaux')

@section('content')
@php $labels = \App\Models\SocialPost::PLATFORMS; @endphp

@if (empty($available))
    <div class="mb-4 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-800 ring-1 ring-amber-200">
        Aucune plateforme configurée. Ajoutez vos identifiants dans
        <a href="{{ route('admin.settings.edit') }}" class="font-semibold underline">Paramètres → Publication réseaux sociaux</a>.
    </div>
@endif

<form method="get" class="mb-4 flex flex-wrap gap-2">
    <input name="q" value="{{ request('q') }}" placeholder="Rechercher un produit…" class="input w-64">
    <a href="{{ route('admin.social.index') }}" class="btn-ghost {{ !request('filter') ? 'ring-2 ring-brand-500' : '' }}">Tous</a>
    <a href="{{ route('admin.social.index', ['filter' => 'new']) }}" class="btn-ghost {{ request('filter')==='new' ? 'ring-2 ring-brand-500' : '' }}">Nouveautés</a>
</form>

<form action="{{ route('admin.social.publish') }}" method="post"
      onsubmit="return confirm('Publier les produits sélectionnés sur les réseaux choisis ?')">
    @csrf

    {{-- Platform picker --}}
    <div class="card mb-4 p-4">
        <div class="flex flex-wrap items-center gap-4">
            <span class="text-sm font-semibold">Publier sur :</span>
            @foreach ($labels as $key => $lbl)
                <label class="flex items-center gap-2 text-sm {{ in_array($key, $available) ? '' : 'opacity-40' }}">
                    <input type="checkbox" name="platforms[]" value="{{ $key }}" class="rounded"
                           @checked(in_array($key, $available)) @disabled(!in_array($key, $available))>
                    {{ $lbl }} @unless(in_array($key, $available))<span class="text-xs">(non configuré)</span>@endunless
                </label>
            @endforeach
            <button class="btn-primary ms-auto">📣 Publier la sélection</button>
        </div>
    </div>

    {{-- Product grid with checkboxes --}}
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
        @foreach ($products as $p)
            <label class="card cursor-pointer overflow-hidden ring-2 ring-transparent transition has-[:checked]:ring-brand-500">
                <div class="relative aspect-square bg-slate-100">
                    <img src="{{ $p->card_image_url }}" alt="" loading="lazy" class="h-full w-full object-cover">
                    <input type="checkbox" name="product_ids[]" value="{{ $p->id }}" class="absolute left-2 top-2 h-5 w-5 rounded">
                    @if ($p->is_new)<span class="absolute right-2 top-2 badge bg-brand-600 text-white">Nouveau</span>@endif
                </div>
                <div class="p-2.5">
                    <p class="line-clamp-1 text-sm font-medium">{{ $p->name_fr }}</p>
                    <p class="text-sm font-bold text-brand-700">@money($p->price)</p>
                </div>
            </label>
        @endforeach
    </div>
    <div class="mt-4">{{ $products->links() }}</div>
</form>

{{-- Recent posts log --}}
<h2 class="mt-8 font-display text-lg font-bold">Historique des publications</h2>
<div class="mt-3 card overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-xs uppercase text-slate-500">
            <tr>
                <th class="px-4 py-2.5 text-start">Date</th>
                <th class="px-4 py-2.5 text-start">Produit</th>
                <th class="px-4 py-2.5 text-start">Plateforme</th>
                <th class="px-4 py-2.5 text-start">Résultat</th>
                <th class="px-4 py-2.5 text-start">Par</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse ($posts as $post)
                <tr>
                    <td class="px-4 py-2.5 text-slate-500">{{ $post->created_at->format('d/m/Y H:i') }}</td>
                    <td class="px-4 py-2.5">{{ optional($post->product)->name_fr ?? '—' }}</td>
                    <td class="px-4 py-2.5">{{ $labels[$post->platform] ?? $post->platform }}</td>
                    <td class="px-4 py-2.5">
                        @if ($post->status === 'success')
                            <span class="badge bg-green-50 text-green-700">✓ Publié</span>
                            @if ($post->permalink)<a href="{{ $post->permalink }}" target="_blank" class="ms-1 text-xs text-brand-700 hover:underline">voir</a>@endif
                        @else
                            <span class="badge bg-red-50 text-red-700" title="{{ $post->message }}">✗ Échec</span>
                        @endif
                    </td>
                    <td class="px-4 py-2.5 text-slate-400">{{ optional($post->author)->name ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-4 py-8 text-center text-slate-400">Aucune publication pour l'instant.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
