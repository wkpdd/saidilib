@extends('admin.layout')
@section('title', 'Livraison')
@section('heading', 'Tarifs de livraison (58 wilayas)')

@section('content')
{{-- Import the carrier's own grid: fills the form, saves nothing by itself. --}}
<form method="get" class="card mb-4 flex flex-wrap items-end gap-3 p-4">
    <input type="hidden" name="noest" value="1">
    <div>
        <h2 class="font-semibold">🚚 Tarifs Noest</h2>
        <p class="text-xs text-slate-400">Charge la grille officielle de Noest (votre grille partenaire) dans le tableau ci-dessous.</p>
    </div>
    <div>
        <label class="label">Supplément (DA)</label>
        <input name="marge" type="number" min="0" step="10" value="{{ $margin ?: '' }}" class="input w-32" placeholder="0">
    </div>
    <button class="btn-ghost">🔄 Charger les tarifs Noest</button>
    @if ($noest)
        <a href="{{ route('admin.wilayas.index') }}" class="text-xs text-slate-400 underline">annuler l'aperçu</a>
    @endif
</form>

@if ($noest)
    <div class="mb-4 rounded-2xl bg-amber-50 p-4 text-sm text-amber-900 ring-1 ring-amber-200">
        ⚠️ Ceci est un <b>aperçu</b> : les prix ci-dessous viennent de Noest et ne sont pas encore enregistrés.
        Les lignes modifiées sont surlignées. Cliquez sur « 💾 Enregistrer tout » pour les appliquer,
        ou quittez la page pour les ignorer.
        @if ($served)
            <br>Les wilayas que Noest ne dessert pas sont <b>décochées</b> : après enregistrement,
            le client ne pourra plus les choisir à la commande.
        @endif
    </div>
@endif

<form action="{{ route('admin.wilayas.update') }}" method="post">
    @csrf @method('PATCH')
    <div class="mb-4 flex items-center justify-between">
        <p class="text-sm text-slate-500">Définissez les frais « à domicile » et « stop desk » par wilaya.</p>
        <button class="btn-primary">💾 Enregistrer tout</button>
    </div>
    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3 text-start">#</th>
                        <th class="px-4 py-3 text-start">Wilaya</th>
                        <th class="px-4 py-3 text-start">À domicile (DA)</th>
                        <th class="px-4 py-3 text-start">Stop desk (DA)</th>
                        @if ($noest)<th class="px-4 py-3 text-start">Actuel</th>@endif
                        <th class="px-4 py-3 text-start">Active</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($wilayas as $w)
                        @php
                            $fees = $noest[$w->code] ?? null;
                            $home = $fees ? (int) round($fees['home'] + $margin) : (int) $w->home_fee;
                            $desk = $fees ? (int) round($fees['stopdesk'] + $margin) : (int) $w->stopdesk_fee;
                            $changed = $fees && ($home !== (int) $w->home_fee || $desk !== (int) $w->stopdesk_fee);
                            // When Noest tells us which wilayas it serves, mirror that on the
                            // storefront: an unserved wilaya must not be selectable at checkout.
                            $servedByNoest = $served === null ? null : isset($served[$w->code]);
                            $active = $servedByNoest === null ? $w->is_active : $servedByNoest;
                        @endphp
                        <tr class="{{ $changed ? 'bg-amber-50' : 'hover:bg-slate-50' }}">
                            <td class="px-4 py-2 text-slate-400">{{ str_pad($w->code, 2, '0', STR_PAD_LEFT) }}</td>
                            <td class="px-4 py-2 font-medium">{{ $w->name_fr }} <span class="text-slate-400" dir="rtl">{{ $w->name_ar }}</span></td>
                            <td class="px-4 py-2"><input name="wilayas[{{ $w->id }}][home_fee]" value="{{ $home }}" type="number" class="input w-28 py-1.5"></td>
                            <td class="px-4 py-2"><input name="wilayas[{{ $w->id }}][stopdesk_fee]" value="{{ $desk }}" type="number" class="input w-28 py-1.5"></td>
                            @if ($noest)
                                <td class="px-4 py-2 text-xs text-slate-400">
                                    @if ($fees)
                                        {{ (int) $w->home_fee }} / {{ (int) $w->stopdesk_fee }}
                                    @else
                                        <span class="badge bg-gray-100 text-gray-700">pas de tarif Noest</span>
                                    @endif
                                    @if ($servedByNoest === false)
                                        <div><span class="badge bg-red-50 text-red-700">non desservie</span></div>
                                    @endif
                                </td>
                            @endif
                            <td class="px-4 py-2"><input type="checkbox" name="wilayas[{{ $w->id }}][is_active]" value="1" @checked($active) class="rounded"></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-4"><button class="btn-primary">💾 Enregistrer tout</button></div>
</form>
@endsection
