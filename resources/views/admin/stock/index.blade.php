@extends('admin.layout')
@section('title', 'Stock multi-emplacements')
@section('heading', '🏬 Stock multi-emplacements')

@section('content')
<div class="grid gap-6 lg:grid-cols-3">
    {{-- Locations manager --}}
    <div class="card p-5">
        <h2 class="mb-3 font-semibold">Emplacements</h2>
        <div class="space-y-2">
            @foreach ($locations as $loc)
                <form method="post" action="{{ route('admin.stock.locations.update', $loc) }}" class="flex items-center gap-2">
                    @csrf @method('PATCH')
                    <input name="name" value="{{ $loc->name }}" class="input flex-1">
                    <label class="flex items-center gap-1 text-xs text-slate-500" title="Emplacement par défaut (ventes)">
                        <input type="radio" name="is_default" value="1" {{ $loc->is_default ? 'checked' : '' }}> défaut
                    </label>
                    <button class="btn-ghost px-2" title="Renommer">💾</button>
                </form>
            @endforeach
        </div>
        <form method="post" action="{{ route('admin.stock.locations.store') }}" class="mt-3 flex gap-2">
            @csrf
            <input name="name" placeholder="Nouvel emplacement…" required class="input flex-1">
            <button class="btn-primary px-3">+ Ajouter</button>
        </form>
        <p class="mt-2 text-xs text-slate-400">Les ventes sortent de l'emplacement par défaut. Les réceptions choisissent leur emplacement.</p>
    </div>

    {{-- Transfer --}}
    <div class="card p-5 lg:col-span-2">
        <h2 class="mb-3 font-semibold">↔️ Transfert entre emplacements</h2>
        <form method="post" action="{{ route('admin.stock.transfer') }}" class="grid gap-3 sm:grid-cols-5">
            @csrf
            <select name="product_id" required class="input sm:col-span-2">
                <option value="">— Produit —</option>
                @foreach (\App\Models\Product::orderBy('name_fr')->get(['id','name_fr','sku']) as $p)
                    <option value="{{ $p->id }}">{{ $p->name_fr }} {{ $p->sku }}</option>
                @endforeach
            </select>
            <select name="from_id" required class="input">
                @foreach ($locations as $loc)<option value="{{ $loc->id }}">De: {{ $loc->name }}</option>@endforeach
            </select>
            <select name="to_id" required class="input">
                @foreach ($locations as $loc)<option value="{{ $loc->id }}" {{ $loop->last ? 'selected' : '' }}>Vers: {{ $loc->name }}</option>@endforeach
            </select>
            <div class="flex gap-2">
                <input type="number" name="qty" min="1" value="1" required class="input w-20">
                <button class="btn-primary flex-1">OK</button>
            </div>
        </form>
        @if (session('success'))<p class="mt-2 text-sm text-green-600">{{ session('success') }}</p>@endif
        @if (session('error'))<p class="mt-2 text-sm text-red-600">{{ session('error') }}</p>@endif
    </div>
</div>

{{-- Matrix --}}
<div class="card mt-6 p-5">
    <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
        <h2 class="font-semibold">Quantités par emplacement</h2>
        <form method="get" class="flex gap-2">
            <input name="q" value="{{ request('q') }}" placeholder="Chercher…" class="input">
            <button class="btn-ghost">🔍</button>
        </form>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-slate-200 text-left text-xs uppercase text-slate-400">
                    <th class="py-2 pr-4">Produit</th>
                    @foreach ($locations as $loc)<th class="py-2 pr-4">{{ $loc->name }}{{ $loc->is_default ? ' ⭐' : '' }}</th>@endforeach
                    <th class="py-2">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($products as $p)
                    @php
                        $rows = [[null, $p->name_fr . ' ' . $p->sku]];
                        foreach ($p->variants as $v) { $rows[] = [$v, '↳ ' . ($v->label_fr ?: ($v->color ?: $v->size))]; }
                    @endphp
                    @foreach ($rows as [$variant, $label])
                        <tr class="border-b border-slate-50 hover:bg-slate-50/60">
                            <td class="py-1.5 pr-4 {{ $variant ? 'pl-4 text-slate-500' : 'font-medium' }}">{{ $label }}</td>
                            @php $rowTotal = 0; @endphp
                            @foreach ($locations as $loc)
                                @php
                                    $key = $p->id . ':' . ($variant->id ?? 0) . '|' . $loc->id;
                                    $qty = $levels[$key]->quantity ?? 0;
                                    $rowTotal += $qty;
                                @endphp
                                <td class="py-1.5 pr-4">
                                    <form method="post" action="{{ route('admin.stock.adjust') }}" class="inline-flex items-center gap-1">
                                        @csrf
                                        <input type="hidden" name="product_id" value="{{ $p->id }}">
                                        <input type="hidden" name="variant_id" value="{{ $variant->id ?? '' }}">
                                        <input type="hidden" name="location_id" value="{{ $loc->id }}">
                                        <input type="number" name="quantity" value="{{ $qty }}" min="0"
                                               class="w-20 rounded-lg border-slate-200 py-1 text-sm"
                                               onchange="this.form.submit()">
                                    </form>
                                </td>
                            @endforeach
                            <td class="py-1.5 font-semibold">{{ $rowTotal }}</td>
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-3">{{ $products->links() }}</div>
    <p class="mt-1 text-xs text-slate-400">Modifier une case ajuste aussi le total vendable du produit (journalisé).</p>
</div>

{{-- Recent movements --}}
<div class="card mt-6 p-5">
    <h2 class="mb-3 font-semibold">Derniers mouvements</h2>
    <div class="space-y-1 text-sm">
        @forelse ($movements as $m)
            <div class="flex items-center gap-2 border-b border-slate-50 py-1">
                <span>{{ ['transfer_in' => '➡️', 'transfer_out' => '⬅️', 'receipt' => '📥', 'sale' => '🛒', 'adjust' => '✏️'][$m->reason] ?? '•' }}</span>
                <span class="flex-1">{{ $m->product_name }} — <b class="{{ $m->delta > 0 ? 'text-green-600' : 'text-red-600' }}">{{ $m->delta > 0 ? '+' : '' }}{{ $m->delta }}</b> @ {{ $m->location_name }}
                    @if ($m->note)<span class="text-slate-400">({{ $m->note }})</span>@endif
                </span>
                <span class="text-xs text-slate-400">{{ \Illuminate\Support\Carbon::parse($m->created_at)->format('d/m H:i') }} {{ $m->user_name ? '· ' . $m->user_name : '' }}</span>
            </div>
        @empty
            <p class="text-slate-400">Aucun mouvement pour le moment.</p>
        @endforelse
    </div>
</div>
@endsection
