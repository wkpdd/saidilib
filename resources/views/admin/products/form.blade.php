@extends('admin.layout')
@section('title', $product->exists ? 'Modifier produit' : 'Nouveau produit')
@section('heading', $product->exists ? 'Modifier : ' . $product->name_fr : 'Nouveau produit')

@section('content')
<form action="{{ $product->exists ? route('admin.products.update', $product) : route('admin.products.store') }}"
      method="post" enctype="multipart/form-data" class="grid gap-6 lg:grid-cols-3">
    @csrf
    @if ($product->exists) @method('PUT') @endif

    <div class="space-y-6 lg:col-span-2">
        {{-- Basic --}}
        <div class="card p-5">
            <h2 class="mb-4 font-semibold">Informations</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label">Nom (Français) *</label>
                    <input name="name_fr" value="{{ old('name_fr', $product->name_fr) }}" required class="input">
                </div>
                <div>
                    <label class="label">Nom (Arabe)</label>
                    <input name="name_ar" value="{{ old('name_ar', $product->name_ar) }}" dir="rtl" class="input">
                </div>
                <div>
                    <label class="label">Marque</label>
                    <input name="brand" value="{{ old('brand', $product->brand) }}" class="input">
                </div>
                <div>
                    <label class="label">Référence (SKU)</label>
                    <input name="sku" value="{{ old('sku', $product->sku) }}" class="input">
                </div>
                <div class="sm:col-span-2">
                    <label class="label">Description courte (FR)</label>
                    <input name="short_desc_fr" value="{{ old('short_desc_fr', $product->short_desc_fr) }}" class="input">
                </div>
                <div class="sm:col-span-2">
                    <label class="label">Description courte (AR)</label>
                    <input name="short_desc_ar" value="{{ old('short_desc_ar', $product->short_desc_ar) }}" dir="rtl" class="input">
                </div>
                <div class="sm:col-span-2">
                    <label class="label">Description (FR) — HTML autorisé</label>
                    <textarea name="description_fr" rows="4" class="input">{{ old('description_fr', $product->description_fr) }}</textarea>
                </div>
                <div class="sm:col-span-2">
                    <label class="label">Description (AR)</label>
                    <textarea name="description_ar" rows="4" dir="rtl" class="input">{{ old('description_ar', $product->description_ar) }}</textarea>
                </div>
            </div>
        </div>

        {{-- Images (selected by picture) --}}
        <div class="card p-5">
            <h2 class="mb-1 font-semibold">Photos</h2>
            <p class="mb-4 text-xs text-slate-400">La 1ʳᵉ image est l'image principale. Une taille peut être liée à une photo.</p>

            @if ($product->exists && $product->images->isNotEmpty())
                <div class="mb-4 grid grid-cols-3 gap-3 sm:grid-cols-4">
                    @foreach ($product->images as $img)
                        <label class="relative block overflow-hidden rounded-xl ring-1 ring-slate-200">
                            <img src="{{ $img->url }}" class="aspect-square w-full object-cover">
                            <span class="absolute inset-x-0 bottom-0 flex items-center justify-between bg-black/50 px-2 py-1 text-[11px] text-white">
                                #{{ $img->id }}
                                <input type="checkbox" name="delete_images[]" value="{{ $img->id }}" title="Supprimer" class="rounded">
                            </span>
                        </label>
                    @endforeach
                </div>
                <p class="mb-3 text-xs text-slate-400">Cochez une image pour la supprimer à l'enregistrement.</p>
            @endif

            <label class="label">Téléverser des images</label>
            <input type="file" name="images[]" multiple accept="image/*" class="input">
            <label class="label mt-3">…ou coller des URLs (une par ligne)</label>
            <textarea name="image_urls" rows="2" class="input" placeholder="https://…"></textarea>

            @if ($product->exists)
                <button type="button" id="openImageSearch" class="btn-ghost mt-3">
                    🔍 Rechercher des images (Google) — logo ajouté automatiquement
                </button>
            @else
                <p class="mt-3 text-xs text-slate-400">💡 Enregistrez d'abord le produit pour utiliser la recherche d'images Google.</p>
            @endif
        </div>

        {{-- Sizes / variants --}}
        <div class="card p-5">
            <div class="mb-1 flex items-center justify-between">
                <h2 class="font-semibold">Couleurs / Tailles / Stock</h2>
                <button type="button" onclick="addVariant()" class="text-sm font-semibold text-brand-700">+ Ajouter</button>
            </div>
            <p class="mb-3 text-xs text-slate-400">Une ligne = une combinaison <b>couleur + taille</b> avec son propre stock. Le <b>nom de la couleur est facultatif</b> — choisir la pastille suffit. La colonne <b>Photo</b> lie la couleur à une image. Laissez <b>Stock</b> vide si vous ne suivez pas le stock par variante (aucune option ne sera bloquée).</p>

            {{-- Recent colours — remembered on this device, click to reuse instantly --}}
            <div id="recentColors" class="mb-3 hidden">
                <p class="mb-1 text-xs font-semibold text-slate-500">🕘 Couleurs récentes (cliquer pour ajouter une ligne)</p>
                <div data-chips class="flex flex-wrap gap-1.5"></div>
            </div>

            @php $imgOpts = $product->exists ? $product->images->values() : collect(); @endphp
            <div class="mb-1 hidden grid-cols-12 gap-2 px-1 text-[11px] font-semibold uppercase text-slate-400 sm:grid">
                <span class="col-span-3">Couleur (optionnel)</span><span class="col-span-1">●</span><span class="col-span-2">Taille</span><span class="col-span-1">+Prix</span><span class="col-span-2">Stock</span><span class="col-span-2">Photo</span><span class="col-span-1"></span>
            </div>
            <div id="variants" class="space-y-2">
                @foreach (old('variants', $product->variants->toArray() ?: []) as $i => $v)
                    <div class="grid grid-cols-12 items-center gap-2" data-variant-row>
                        <input type="hidden" name="variants[{{ $i }}][id]" value="{{ $v['id'] ?? '' }}">
                        <input type="hidden" name="variants[{{ $i }}][has_color]" value="{{ (!empty($v['color']) || !empty($v['color_hex'])) ? 1 : 0 }}" data-has-color>
                        <input name="variants[{{ $i }}][color]" value="{{ $v['color'] ?? '' }}" placeholder="Rouge (optionnel)" class="input col-span-3" data-color-name>
                        <input name="variants[{{ $i }}][color_hex]" value="{{ $v['color_hex'] ?? '#000000' }}" type="color" class="h-10 w-full col-span-1 rounded-lg border border-slate-200" data-color-hex>
                        <input name="variants[{{ $i }}][size]" value="{{ $v['size'] ?? '' }}" placeholder="L / 24x32" class="input col-span-2">
                        <input name="variants[{{ $i }}][price_delta]" value="{{ $v['price_delta'] ?? 0 }}" type="number" step="any" placeholder="+ Prix" class="input col-span-1">
                        <input name="variants[{{ $i }}][stock]" value="{{ $v['stock'] ?? '' }}" type="number" min="0" placeholder="Stock (vide = illimité)" class="input col-span-2">
                        <select name="variants[{{ $i }}][image_id]" class="input col-span-2 text-xs">
                            <option value="">— Photo —</option>
                            @foreach ($imgOpts as $k => $im)
                                <option value="{{ $im->id }}" @selected(($v['image_id'] ?? null) == $im->id)>Photo {{ $k + 1 }}</option>
                            @endforeach
                        </select>
                        <button type="button" onclick="this.closest('[data-variant-row]').remove()" class="col-span-1 text-red-500">✕</button>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Sidebar --}}
    <div class="space-y-6">
        <div class="card p-5">
            <h2 class="mb-4 font-semibold">Prix & stock</h2>
            <label class="label">Prix détail (DA) *</label>
            <input name="price" type="number" step="any" value="{{ old('price', $product->price) }}" required class="input mb-3">
            <label class="label">Ancien prix (barré)</label>
            <input name="compare_at_price" type="number" step="any" value="{{ old('compare_at_price', $product->compare_at_price) }}" class="input mb-3">
            <div class="mb-3 grid grid-cols-2 gap-2 rounded-xl bg-brand-50 p-3">
                <div>
                    <label class="label text-xs">💼 Prix grossiste</label>
                    <input name="wholesale_price" type="number" step="any" value="{{ old('wholesale_price', $product->wholesale_price) }}" class="input" placeholder="—">
                </div>
                <div>
                    <label class="label text-xs">🏭 Super grossiste</label>
                    <input name="super_wholesale_price" type="number" step="any" value="{{ old('super_wholesale_price', $product->super_wholesale_price) }}" class="input" placeholder="—">
                </div>
                <p class="col-span-2 text-[11px] text-slate-400">Appliqués automatiquement aux clients connectés selon leur catégorie. Vides = prix détail.</p>
            </div>
            <label class="label">Stock</label>
            <input name="stock" type="number" value="{{ old('stock', $product->stock ?? 0) }}" class="input mb-3">
            <label class="flex items-center gap-2 text-sm"><input type="hidden" name="track_stock" value="0"><input type="checkbox" name="track_stock" value="1" @checked(old('track_stock', $product->track_stock)) class="rounded"> Gérer le stock</label>
        </div>

        <div class="card p-5">
            <h2 class="mb-4 font-semibold">Organisation</h2>
            <label class="label">Catégorie</label>
            <select name="category_id" class="input mb-4">
                <option value="">—</option>
                @foreach ($categories as $cat)
                    <option value="{{ $cat->id }}" @selected(old('category_id', $product->category_id)==$cat->id)>{{ $cat->name_fr }}</option>
                @endforeach
            </select>
            <div class="space-y-2 text-sm">
                <label class="flex items-center gap-2"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $product->is_active)) class="rounded"> Actif (visible)</label>
                <label class="flex items-center gap-2"><input type="hidden" name="is_featured" value="0"><input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $product->is_featured)) class="rounded"> En vedette</label>
                <label class="flex items-center gap-2"><input type="hidden" name="is_new" value="0"><input type="checkbox" name="is_new" value="1" @checked(old('is_new', $product->is_new)) class="rounded"> Nouveau</label>
            </div>
        </div>

        @if (isset($pixels) && $pixels->isNotEmpty())
        <div class="card p-5">
            <h2 class="mb-3 font-semibold">🎯 Pixels (cette page)</h2>
            <div class="space-y-2 text-sm">
                @foreach ($pixels as $px)
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="pixels[]" value="{{ $px->id }}" class="rounded"
                            @checked($product->exists && $product->pixels->contains($px->id))>
                        {{ $px->name }} <span class="text-xs text-slate-400">({{ $px->provider }})</span>
                    </label>
                @endforeach
            </div>
        </div>
        @endif

        @if ($product->exists)
            <div class="card p-5">
                <h2 class="mb-1 font-semibold">📣 Publier sur les réseaux</h2>
                <p class="mb-1 text-xs text-slate-400">Partagez la fiche produit publique en un clic (aperçu riche avec image &amp; prix).</p>
                @include('partials.share', ['product' => $product, 'url' => route('product', $product->slug)])
            </div>
        @endif

        <div class="card p-5">
            <button class="btn-primary w-full">💾 Enregistrer</button>
            <a href="{{ route('admin.products.index') }}" class="btn-ghost mt-2 w-full">Annuler</a>
        </div>
    </div>
</form>

@if ($product->exists)
    {{-- Google Image Search modal --}}
    <div id="imageSearchModal" class="fixed inset-0 z-[100] hidden items-center justify-center bg-black/70 p-4">
        <div class="max-h-[85vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white p-5">
            <div class="mb-3 flex items-center justify-between">
                <h3 class="font-semibold text-ink-900">🔍 Rechercher des images</h3>
                <button type="button" id="closeImageSearch" class="grid h-8 w-8 place-items-center rounded-lg hover:bg-slate-100">✕</button>
            </div>
            <form id="imageSearchForm" class="flex gap-2">
                <input type="text" id="imageSearchQuery" value="{{ $product->name_fr }}" class="input flex-1" placeholder="Terme de recherche…">
                <button type="submit" class="btn-primary">Rechercher</button>
            </form>
            <p class="mt-2 text-xs text-amber-600">⚠️ Utilisez uniquement des images libres de droits ou dont vous avez l'autorisation. Le logo de la boutique sera ajouté en bas à droite.</p>
            <div id="imageSearchStatus" class="mt-3 text-sm text-slate-500"></div>
            <div id="imageSearchResults" class="mt-3 grid grid-cols-3 gap-2 sm:grid-cols-4"></div>
        </div>
    </div>
@endif

@push('scripts')
<script>
    let vIndex = {{ count(old('variants', $product->variants->toArray() ?: [])) }};
    const imgOptions = @json($imgOpts->map(fn ($im, $k) => ['id' => $im->id, 'label' => 'Photo ' . ($k + 1)])->values());
    function imgOptionsHtml() {
        return '<option value="">— Photo —</option>' +
            imgOptions.map((o) => `<option value="${o.id}">${o.label}</option>`).join('');
    }
    function addVariant(prefill) {
        const row = document.createElement('div');
        row.className = 'grid grid-cols-12 items-center gap-2';
        row.setAttribute('data-variant-row', '');
        const hex = prefill?.hex || '#000000';
        const name = prefill?.name || '';
        row.innerHTML = `
            <input type="hidden" name="variants[${vIndex}][has_color]" value="${prefill ? 1 : 0}" data-has-color>
            <input name="variants[${vIndex}][color]" value="${name}" placeholder="Rouge (optionnel)" class="input col-span-3" data-color-name>
            <input name="variants[${vIndex}][color_hex]" type="color" value="${hex}" class="h-10 w-full col-span-1 rounded-lg border border-slate-200" data-color-hex>
            <input name="variants[${vIndex}][size]" placeholder="L / 24x32" class="input col-span-2">
            <input name="variants[${vIndex}][price_delta]" type="number" step="any" placeholder="+ Prix" class="input col-span-1">
            <input name="variants[${vIndex}][stock]" type="number" min="0" placeholder="Stock (vide = illimité)" class="input col-span-2">
            <select name="variants[${vIndex}][image_id]" class="input col-span-2 text-xs">${imgOptionsHtml()}</select>
            <button type="button" class="col-span-1 text-red-500">✕</button>`;
        row.querySelector('button').addEventListener('click', () => row.remove());
        document.getElementById('variants').appendChild(row);
        vIndex++;
    }

    // Picking a colour swatch (even without typing a name) marks the row as a
    // "colour" variant, so the storefront picker shows it as a swatch — and
    // remembers it on this device so it can be reused instantly next time.
    document.getElementById('variants').addEventListener('input', (e) => {
        if (e.target.matches('[data-color-hex]')) {
            const row = e.target.closest('[data-variant-row]');
            const flag = row.querySelector('[data-has-color]');
            if (flag) flag.value = '1';
            const nameInput = row.querySelector('[data-color-name]');
            saveRecentColor(nameInput ? nameInput.value.trim() : '', e.target.value);
        }
    });
    // A typed colour name (with a colour already picked) also updates the
    // remembered entry's label.
    document.getElementById('variants').addEventListener('change', (e) => {
        if (e.target.matches('[data-color-name]')) {
            const row = e.target.closest('[data-variant-row]');
            if (row.querySelector('[data-has-color]')?.value === '1') {
                saveRecentColor(e.target.value.trim(), row.querySelector('[data-color-hex]').value);
            }
        }
    });

    // ── Recent colours (remembered on this device via localStorage) ──────
    const RECENT_COLORS_KEY = 'saidi_recent_colors';

    function getRecentColors() {
        try { return JSON.parse(localStorage.getItem(RECENT_COLORS_KEY) || '[]'); } catch (e) { return []; }
    }
    function saveRecentColor(name, hex) {
        if (!hex) return;
        let list = getRecentColors().filter((c) => c.hex.toLowerCase() !== hex.toLowerCase());
        list.unshift({ name, hex });
        localStorage.setItem(RECENT_COLORS_KEY, JSON.stringify(list.slice(0, 12)));
        renderRecentColors();
    }
    function renderRecentColors() {
        const wrap = document.getElementById('recentColors');
        if (!wrap) return;
        const list = getRecentColors();
        wrap.classList.toggle('hidden', list.length === 0);
        wrap.querySelector('[data-chips]').innerHTML = list.map((c) => `
            <button type="button" data-recent-chip data-hex="${c.hex}" data-name="${(c.name || '').replace(/"/g, '&quot;')}"
                    title="${c.name || c.hex}"
                    class="grid h-8 w-8 place-items-center rounded-full ring-1 ring-slate-200 transition hover:ring-2 hover:ring-brand-500"
                    style="background:${c.hex}"></button>`).join('');
    }
    document.getElementById('recentColors')?.addEventListener('click', (e) => {
        const chip = e.target.closest('[data-recent-chip]');
        if (!chip) return;
        addVariant({ name: chip.dataset.name, hex: chip.dataset.hex });
    });

    // Seed the recent list from whatever colours this product already has
    // saved, so the list isn't empty on a fresh page load.
    document.querySelectorAll('#variants [data-variant-row]').forEach((row) => {
        if (row.querySelector('[data-has-color]')?.value === '1') {
            const hex = row.querySelector('[data-color-hex]')?.value;
            const name = row.querySelector('[data-color-name]')?.value?.trim();
            if (hex) saveRecentColor(name, hex);
        }
    });
    renderRecentColors();

    @if ($product->exists)
    // ── Google Image Search modal ─────────────────────────────────────────
    const searchModal = document.getElementById('imageSearchModal');
    if (searchModal) {
        const openBtn = document.getElementById('openImageSearch');
        const closeBtn = document.getElementById('closeImageSearch');
        const form = document.getElementById('imageSearchForm');
        const queryInput = document.getElementById('imageSearchQuery');
        const status = document.getElementById('imageSearchStatus');
        const results = document.getElementById('imageSearchResults');

        openBtn.addEventListener('click', () => {
            searchModal.classList.remove('hidden');
            searchModal.classList.add('flex');
        });
        closeBtn.addEventListener('click', () => {
            searchModal.classList.add('hidden');
            searchModal.classList.remove('flex');
        });

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const q = queryInput.value.trim();
            if (!q) return;
            status.textContent = 'Recherche…';
            results.innerHTML = '';
            try {
                const res = await fetch(`{{ route('admin.products.imagesearch.search') }}?q=${encodeURIComponent(q)}`, {
                    headers: { 'Accept': 'application/json' },
                });
                const data = await res.json();
                if (!data.ok) {
                    status.textContent = data.error || 'Erreur de recherche.';
                    return;
                }
                if (!data.results || !data.results.length) {
                    status.textContent = 'Aucun résultat.';
                    return;
                }
                status.textContent = `${data.results.length} résultat(s) — cliquez pour importer.`;
                results.innerHTML = data.results.map((r) => `
                    <button type="button" data-img-url="${r.image.replace(/"/g, '&quot;')}"
                            class="group overflow-hidden rounded-lg ring-1 ring-slate-200 hover:ring-2 hover:ring-brand-500">
                        <img src="${r.thumbnail}" class="aspect-square w-full object-cover" loading="lazy" alt="">
                    </button>`).join('');
            } catch (err) {
                status.textContent = 'Erreur de connexion.';
            }
        });

        results.addEventListener('click', async (e) => {
            const btn = e.target.closest('[data-img-url]');
            if (!btn) return;
            status.textContent = 'Import en cours…';
            btn.disabled = true;
            try {
                const res = await fetch('{{ route('admin.products.imagesearch.attach', $product) }}', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
                            || document.querySelector('input[name=_token]').value,
                    },
                    body: JSON.stringify({ image_url: btn.dataset.imgUrl }),
                });
                const data = await res.json();
                if (data.ok) {
                    status.textContent = 'Image importée ✓ — actualisation…';
                    location.reload();
                } else {
                    status.textContent = data.error || "Échec de l'import.";
                    btn.disabled = false;
                }
            } catch (err) {
                status.textContent = 'Erreur de connexion.';
                btn.disabled = false;
            }
        });
    }
    @endif
</script>
@endpush
@endsection
