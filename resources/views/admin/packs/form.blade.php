@extends('admin.layout')
@section('title', $pack->exists ? 'Modifier le pack' : 'Nouveau pack')
@section('heading', $pack->exists ? 'Modifier : ' . $pack->name_fr : '🎒 Nouveau pack scolaire')

@section('content')
<form action="{{ $pack->exists ? route('admin.packs.update', $pack) : route('admin.packs.store') }}"
      method="post" enctype="multipart/form-data" class="grid gap-6 lg:grid-cols-3">
    @csrf
    @if ($pack->exists) @method('PUT') @endif

    <div class="space-y-6 lg:col-span-2">
        <div class="card p-5">
            <h2 class="mb-4 font-semibold">Informations</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label">Nom (Français) *</label>
                    <input name="name_fr" value="{{ old('name_fr', $pack->name_fr) }}" required class="input" placeholder="Pack 1ère année moyenne">
                </div>
                <div>
                    <label class="label">Nom (Arabe)</label>
                    <input name="name_ar" value="{{ old('name_ar', $pack->name_ar) }}" dir="rtl" class="input" placeholder="حزمة السنة الأولى متوسط">
                </div>
                <div class="sm:col-span-2">
                    <label class="label">Description (FR)</label>
                    <textarea name="description_fr" rows="2" class="input">{{ old('description_fr', $pack->description_fr) }}</textarea>
                </div>
                <div class="sm:col-span-2">
                    <label class="label">Description (AR)</label>
                    <textarea name="description_ar" rows="2" dir="rtl" class="input">{{ old('description_ar', $pack->description_ar) }}</textarea>
                </div>
            </div>
        </div>

        <div class="card p-5">
            <h2 class="font-semibold">Articles du pack</h2>
            <p class="mb-3 text-xs text-slate-400">La liste unifiée des fournitures. La quantité par élève pour chaque article.</p>

            <div class="mb-3 flex gap-2">
                <div class="relative flex-1">
                    <input id="packSearch" type="text" autocomplete="off" class="input pe-9"
                           placeholder="🔍 Chercher un produit (nom, référence, marque)…"
                           aria-label="Chercher un produit à ajouter au pack">
                    <button type="button" id="packSearchClear" class="absolute end-2 top-1/2 hidden -translate-y-1/2 rounded-lg px-2 py-1 text-slate-400 hover:bg-slate-100" tabindex="-1" aria-label="Effacer">✕</button>
                    <div id="packResults" class="absolute inset-x-0 top-full z-20 mt-1 hidden max-h-72 overflow-y-auto rounded-xl bg-white py-1 shadow-card ring-1 ring-slate-200"></div>
                </div>
                <button type="button" id="packScan" class="btn-ghost shrink-0 px-3" title="Scanner un code-barres">📷</button>
            </div>

            {{-- Lignes existantes : même structure que le gabarit JS ci-dessous. --}}
            <div id="packItems" class="space-y-2">
                @foreach (old('items', $pack->items->map(fn ($i) => ['product_id' => $i->product_id, 'variant_id' => $i->product_variant_id, 'quantity' => $i->quantity])->toArray()) as $item)
                    @php $p = $products[$item['product_id'] ?? null] ?? null; @endphp
                    @continue(! $p)
                    <div class="flex items-center gap-2 rounded-xl bg-slate-50 px-3 py-2" data-pack-item data-product="{{ $p['i'] }}" data-price="{{ $p['p'] }}">
                        <input type="hidden" name="items[{{ $loop->index }}][product_id]" value="{{ $p['i'] }}">
                        <input type="hidden" name="items[{{ $loop->index }}][variant_id]" value="{{ $item['variant_id'] ?? '' }}">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium">{{ $p['n'] }} @if ($p['x'])<span class="badge bg-slate-200 text-slate-500">inactif</span>@endif</p>
                            <p class="truncate text-xs text-slate-400">{{ trim(implode(' · ', array_filter([$p['s'], $p['b'], number_format($p['p'], 0, ',', ' ') . ' DA']))) }}</p>
                        </div>
                        <input type="number" name="items[{{ $loop->index }}][quantity]" value="{{ $item['quantity'] ?? 1 }}" min="1" max="999" class="input w-20 py-1.5 text-center" data-qty aria-label="Quantité">
                        <button type="button" class="rounded-lg px-2 py-1 text-red-500 hover:bg-red-50" data-remove aria-label="Retirer">✕</button>
                    </div>
                @endforeach
            </div>
            <p id="packEmpty" class="hidden rounded-xl border border-dashed border-slate-200 p-6 text-center text-sm text-slate-400">
                Aucun article — utilisez la recherche ci-dessus pour composer le pack.
            </p>
            <div class="mt-3 flex items-center justify-between border-t border-slate-100 pt-3 text-sm">
                <span class="text-slate-500"><b id="packCount">0</b> article(s)</span>
                <span class="text-slate-500">Somme : <b id="packSum" class="text-ink-900">0 DA</b></span>
            </div>
        </div>
    </div>

    <div class="space-y-6">
        <div class="card p-5">
            <h2 class="mb-4 font-semibold">Publication</h2>
            <label class="mb-3 flex items-center gap-2">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $pack->is_active)) class="rounded">
                <span class="text-sm">Pack visible (dans la section Packs)</span>
            </label>
            <label class="label">Prix promo du pack (DA, optionnel)</label>
            <input name="price" type="number" step="any" min="0" value="{{ old('price', $pack->price ? (float) $pack->price : '') }}" class="input" placeholder="vide = somme des articles">
            @if ($pack->exists)
                <p class="mt-2 text-xs text-slate-500">Somme actuelle des articles : <b>{{ number_format($pack->items_total, 0, ',', ' ') }} DA</b></p>
            @endif
            <p class="mt-1 text-xs text-slate-400">S'il est inférieur à la somme, le client paie ce prix (remise répartie sur les lignes).</p>
        </div>

        <div class="card p-5">
            <h2 class="mb-4 font-semibold">Photo du pack</h2>
            @if ($pack->image_url)
                <img src="{{ $pack->image_url }}" class="mb-3 aspect-video w-full rounded-xl object-cover">
            @endif
            <input type="file" name="image" accept="image/*" class="input">
            <p class="mt-1 text-xs text-slate-400">Compressée automatiquement.</p>
        </div>

        <button class="btn-primary w-full py-3">💾 Enregistrer le pack</button>
        @if (session('success'))<p class="text-center text-sm text-green-600">{{ session('success') }}</p>@endif
    </div>
</form>
@endsection

@push('scripts')
<script>
(function () {
    @php $packProductsJs = $products->values(); @endphp
    // Le catalogue part avec la page : la recherche est locale, aucun aller-retour réseau.
    const PRODUCTS = @json($packProductsJs);
    const MAX_RESULTS = 25;

    const search  = document.getElementById('packSearch');
    const clearBt = document.getElementById('packSearchClear');
    const results = document.getElementById('packResults');
    const list    = document.getElementById('packItems');
    const empty   = document.getElementById('packEmpty');

    let packIdx = {{ count(old('items', $pack->items->toArray() ?: [])) }};
    let matches = [];
    let active  = 0;

    // Insensible aux accents : « ecolier » trouve « écolier ».
    const fold = (s) => (s || '').toString().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    // Index de recherche : nom FR + nom AR + référence + marque, calculé une fois.
    PRODUCTS.forEach((p) => { p.f = fold([p.n, p.a, p.s, p.b].filter(Boolean).join(' ')); });

    const money = (n) => Math.round(n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' DA';
    const esc = (s) => (s || '').replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
    const sub = (p) => [p.s, p.b, money(p.p)].filter(Boolean).join(' · ');

    // --- Recherche -----------------------------------------------------

    function find(q) {
        const terms = fold(q).split(/\s+/).filter(Boolean);
        if (!terms.length) return PRODUCTS.slice(0, MAX_RESULTS + 1);   // +1 : déclenche l'indice « affinez »
        const out = [];
        for (const p of PRODUCTS) {
            if (terms.every((t) => p.f.includes(t))) {
                out.push(p);
                if (out.length > MAX_RESULTS) break;
            }
        }
        return out;
    }

    function render() {
        const shown = matches.slice(0, MAX_RESULTS);
        if (!shown.length) {
            results.innerHTML = '<p class="px-3 py-4 text-center text-sm text-slate-400">Aucun produit trouvé.</p>';
        } else {
            results.innerHTML = shown.map((p, i) => `
                <button type="button" data-pick="${p.i}" data-i="${i}"
                        class="flex w-full items-center gap-2 px-3 py-2 text-start ${i === active ? 'bg-brand-50' : ''}">
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-medium">${esc(p.n)}${p.x ? ' <span class="badge bg-slate-200 text-slate-500">inactif</span>' : ''}</span>
                        <span class="block truncate text-xs text-slate-400">${esc(sub(p))}</span>
                    </span>
                    ${inPack(p.i) ? '<span class="badge bg-green-50 text-green-700">✓ dans le pack</span>' : '<span class="text-xs text-brand-700">+ ajouter</span>'}
                </button>`).join('')
                + (matches.length > MAX_RESULTS
                    ? `<p class="px-3 py-2 text-center text-xs text-slate-400">Affinez la recherche — ${PRODUCTS.length} produits au catalogue.</p>`
                    : '');
        }
        results.classList.remove('hidden');
        clearBt.classList.toggle('hidden', !search.value);
    }

    function close() {
        results.classList.add('hidden');
    }

    function open() {
        matches = find(search.value);
        active = 0;
        render();
    }

    function moveActive(delta) {
        const max = Math.min(matches.length, MAX_RESULTS);
        if (!max) return;
        active = (active + delta + max) % max;
        render();
        const el = results.querySelector(`[data-i="${active}"]`);
        if (el) el.scrollIntoView({ block: 'nearest' });
    }

    // --- Lignes du pack ------------------------------------------------

    const inPack = (id) => list.querySelector(`[data-product="${id}"]`);

    function add(id) {
        const p = PRODUCTS.find((x) => x.i == id);
        if (!p) return;
        const existing = inPack(id);
        if (existing) {                       // déjà présent : on incrémente au lieu de dupliquer
            const qty = existing.querySelector('[data-qty]');
            qty.value = Math.min(999, (parseInt(qty.value, 10) || 0) + 1);
            totals();
            flash(existing);
            return;
        }
        const row = document.createElement('div');
        row.className = 'flex items-center gap-2 rounded-xl bg-slate-50 px-3 py-2';
        row.setAttribute('data-pack-item', '');
        row.dataset.product = p.i;
        row.dataset.price = p.p;
        row.innerHTML = `
            <input type="hidden" name="items[${packIdx}][product_id]" value="${p.i}">
            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-medium">${esc(p.n)}${p.x ? ' <span class="badge bg-slate-200 text-slate-500">inactif</span>' : ''}</p>
                <p class="truncate text-xs text-slate-400">${esc(sub(p))}</p>
            </div>
            <input type="number" name="items[${packIdx}][quantity]" value="1" min="1" max="999" class="input w-20 py-1.5 text-center" data-qty aria-label="Quantité">
            <button type="button" class="rounded-lg px-2 py-1 text-red-500 hover:bg-red-50" data-remove aria-label="Retirer">✕</button>`;
        list.appendChild(row);
        packIdx++;
        totals();
        flash(row);
    }

    function flash(row) {
        row.classList.add('ring-2', 'ring-brand-400');
        setTimeout(() => row.classList.remove('ring-2', 'ring-brand-400'), 700);
        row.scrollIntoView({ block: 'nearest' });
    }

    function totals() {
        const rows = list.querySelectorAll('[data-pack-item]');
        let sum = 0;
        rows.forEach((r) => {
            sum += parseFloat(r.dataset.price || 0) * (parseInt(r.querySelector('[data-qty]').value, 10) || 0);
        });
        document.getElementById('packCount').textContent = rows.length;
        document.getElementById('packSum').textContent = money(sum);
        empty.classList.toggle('hidden', rows.length > 0);
    }

    // --- Événements ----------------------------------------------------

    search.addEventListener('input', open);
    search.addEventListener('focus', open);
    search.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowDown') { e.preventDefault(); moveActive(1); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); moveActive(-1); }
        else if (e.key === 'Enter') {           // jamais de soumission accidentelle du formulaire
            e.preventDefault();
            if (matches[active]) { add(matches[active].i); render(); }
        } else if (e.key === 'Escape') { close(); }
    });

    clearBt.addEventListener('click', () => { search.value = ''; search.focus(); open(); });

    // Scan d'un code-barres : la référence est déjà dans le catalogue chargé,
    // donc la correspondance se fait sans requête réseau.
    document.getElementById('packScan').addEventListener('click', () => {
        window.SaidiScanner?.open((code) => {
            const p = PRODUCTS.find((x) => fold(x.s) === fold(code));
            if (p) {
                add(p.i);
                search.value = '';
            } else {
                search.value = code;               // pas de référence exacte : on pré-remplit la recherche
                alert(`Aucun produit avec la référence « ${code} ».`);
            }
            open();
        });
    });

    results.addEventListener('mousedown', (e) => {
        const btn = e.target.closest('[data-pick]');
        if (!btn) return;
        e.preventDefault();                     // garde le focus dans la recherche pour enchaîner
        active = parseInt(btn.dataset.i, 10) || 0;
        add(btn.dataset.pick);
        render();
    });

    document.addEventListener('click', (e) => {
        if (!e.target.closest('#packSearch, #packResults, #packSearchClear')) close();
    });

    list.addEventListener('click', (e) => {
        if (e.target.closest('[data-remove]')) {
            e.target.closest('[data-pack-item]').remove();
            totals();
            if (!results.classList.contains('hidden')) render();
        }
    });
    list.addEventListener('input', (e) => {
        if (e.target.matches('[data-qty]')) totals();
    });

    totals();
})();
</script>
@endpush

@push('scripts')
    @vite(['resources/js/scanner.js'])
@endpush
