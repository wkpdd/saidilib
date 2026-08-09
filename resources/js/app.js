import './bootstrap';

// ── Toast ────────────────────────────────────────────────────────────────
// Shared by add-to-cart and the share buttons. One at a time: a second call
// replaces the first rather than stacking notes down the screen.
let toastEl = null;
let toastTimer = null;

function toast(message, kind = 'ok') {
    if (toastEl) toastEl.remove();
    clearTimeout(toastTimer);

    toastEl = document.createElement('div');
    toastEl.setAttribute('role', 'status');
    toastEl.textContent = message;
    toastEl.className =
        'fixed inset-x-0 bottom-24 z-50 mx-auto w-fit max-w-[90%] rounded-xl px-4 py-2.5 text-center text-sm font-semibold text-white shadow-card lg:bottom-6 ' +
        (kind === 'error' ? 'bg-red-600' : 'bg-ink-900');
    document.body.appendChild(toastEl);

    toastTimer = setTimeout(() => {
        toastEl?.remove();
        toastEl = null;
    }, 3200);
}

// ── Add to cart without leaving the page ─────────────────────────────────
// The shopper keeps their place in the listing and their scroll position;
// only the header count changes. Without JS the forms still post normally
// and land on the cart, exactly as before.
document.addEventListener('submit', (e) => {
    const form = e.target.closest('[data-cart-form]');
    if (!form) return;

    // "⚡ Commander maintenant" must really navigate (straight to checkout).
    if (e.submitter && e.submitter.name === 'buy_now') return;

    e.preventDefault();

    const btn = e.submitter || form.querySelector('[type=submit]');
    if (form.dataset.busy) return;          // guard against double taps
    form.dataset.busy = '1';
    if (btn) btn.disabled = true;

    const done = () => {
        delete form.dataset.busy;
        if (btn) btn.disabled = false;
    };

    fetch(form.action, {
        method: 'POST',
        body: new FormData(form),
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        credentials: 'same-origin',
    })
        .then(async (res) => {
            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
                toast(data.message || 'Impossible d\'ajouter ce produit.', 'error');
                done();
                return;
            }

            toast(data.message || 'Ajouté au panier ✓');

            const badge = document.querySelector('[data-cart-count]');
            if (badge && typeof data.count === 'number') {
                badge.textContent = data.count;
                // A quick pop so the eye follows the count, not the page.
                badge.classList.add('scale-125');
                setTimeout(() => badge.classList.remove('scale-125'), 220);
            }
            done();
        })
        .catch(() => {
            // Offline or blocked fetch: fall back to the ordinary POST.
            done();
            form.submit();
        });
});

// ── "Vous étiez ici" — return to the listing where you left off ──────────
// ShopTrail sends the shopper back with ?revoir=<id>#p<id>. Centre that card
// and mark it briefly, then clean the URL so a refresh doesn't repeat it.
function markWhereYouWere() {
    const params = new URLSearchParams(window.location.search);
    const id = params.get('revoir');
    if (!id) return;

    const card = document.getElementById('p' + id);
    if (card) {
        const settle = () => card.scrollIntoView({ block: 'center' });
        settle();
        // Lazy images can shift the grid after first paint — re-assert once.
        window.addEventListener('load', settle, { once: true });

        card.classList.add('ring-4', 'ring-brand-400', 'ring-offset-2');

        const badges = card.querySelector('[data-card-badges]');
        let pill = null;
        if (badges) {
            pill = document.createElement('span');
            pill.className = 'badge bg-ink-900/85 text-white';
            pill.textContent = card.dataset.hereLabel || 'Vous étiez ici';
            badges.prepend(pill);
        }

        setTimeout(() => {
            card.classList.remove('ring-4', 'ring-brand-400', 'ring-offset-2');
            pill?.remove();
        }, 4500);
    }

    params.delete('revoir');
    const qs = params.toString();
    window.history.replaceState(null, '', window.location.pathname + (qs ? '?' + qs : ''));
}

// Lightweight progressive-enhancement helpers (no framework needed).
document.addEventListener('DOMContentLoaded', () => {
    markWhereYouWere();

    // Mobile menu toggle
    document.querySelectorAll('[data-toggle]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const target = document.querySelector(btn.dataset.toggle);
            if (target) target.classList.toggle('hidden');
        });
    });

    // Product gallery: clicking a thumbnail swaps the main image
    const mainImg = document.querySelector('[data-main-image]');
    document.querySelectorAll('[data-thumb]').forEach((thumb) => {
        thumb.addEventListener('click', () => {
            if (mainImg) mainImg.src = thumb.dataset.thumb;
            document.querySelectorAll('[data-thumb]').forEach((t) =>
                t.classList.remove('ring-2', 'ring-brand-500'));
            thumb.classList.add('ring-2', 'ring-brand-500');
        });
    });

    // Variant (size) selection: swaps photo + updates price
    const priceEl = document.querySelector('[data-price]');
    const basePrice = priceEl ? parseFloat(priceEl.dataset.price) : 0;
    const currency = priceEl ? priceEl.dataset.currency : '';
    document.querySelectorAll('[data-variant]').forEach((opt) => {
        opt.addEventListener('change', () => {
            const img = opt.dataset.image;
            if (img && mainImg) mainImg.src = img;
            if (priceEl) {
                const delta = parseFloat(opt.dataset.delta || '0');
                const total = basePrice + delta;
                priceEl.textContent = new Intl.NumberFormat('fr-FR').format(total) + ' ' + currency;
            }
        });
    });

    // ── Quantity breaks: highlight the active tier + price for this qty ──
    const tiersRoot = document.querySelector('[data-qty-tiers]');
    const qtyInput = document.querySelector('[data-qty] input[name="qty"]');
    if (tiersRoot && qtyInput) {
        const tiers = JSON.parse(tiersRoot.dataset.qtyTiers || '[]');
        const tierBase = parseFloat(tiersRoot.dataset.basePrice || '0');
        const tierCurrency = tiersRoot.dataset.currency || '';
        const hint = document.querySelector('[data-qty-hint]');
        const fmt = (n) => new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 2 }).format(n) + ' ' + tierCurrency;

        const refresh = () => {
            const qty = Math.max(1, parseInt(qtyInput.value, 10) || 1);
            let pct = 0;
            let activeMin = 0;
            tiers.forEach((t) => {
                if (qty >= t.min && t.pct > pct) { pct = t.pct; activeMin = t.min; }
            });

            tiersRoot.querySelectorAll('[data-tier-row]').forEach((row) => {
                const on = parseInt(row.dataset.tierRow, 10) === activeMin;
                row.classList.toggle('bg-brand-50', on);
                row.classList.toggle('font-bold', on);
            });

            if (hint) {
                hint.textContent = pct > 0
                    ? `${qty} × ${fmt(tierBase * (1 - pct / 100))} (-${pct}%)`
                    : '';
            }
        };

        qtyInput.addEventListener('input', refresh);
        document.querySelectorAll('[data-qty] button').forEach((b) =>
            b.addEventListener('click', () => setTimeout(refresh, 0)));
        refresh();
    }

    // ── Variant picker: colour + size with live availability ────────────
    const vRoot = document.querySelector('[data-variants]');
    if (vRoot) {
        const variants = JSON.parse(vRoot.dataset.variants || '[]');
        const basePrice = parseFloat(vRoot.dataset.basePrice || '0');
        const currency = vRoot.dataset.currency || '';
        // Only gate availability on per-variant stock when the merchant actually
        // tracks stock for this product. Otherwise every option stays selectable
        // (a variant row left at stock=0 by the admin must not brick the picker).
        const trackStock = vRoot.dataset.trackStock === '1';
        // stock === null → this option isn't counted separately: always offered.
        const hasStock = (v) => !trackStock || v.stock === null || v.stock > 0;
        const input = vRoot.querySelector('[data-variant-input]');
        const avail = vRoot.querySelector('[data-availability]');
        const colorLabel = vRoot.querySelector('[data-color-label]');
        const priceEl = document.querySelector('[data-price]');
        const mainImg = document.querySelector('[data-main-image]');
        const hasColors = variants.some((v) => v.color);
        const hasSizes = variants.some((v) => v.size);
        let selColor = null;
        let selColorName = null;
        let selSize = null;
        // Variants that are neither a colour nor a size are picked by name.
        let selVariant = null;

        const thumbs = document.querySelectorAll('[data-thumb]');
        const colorImage = (color) => (variants.find((v) => v.color === color && v.image) || {}).image;
        function swapMain(url) {
            if (!mainImg || !url) return;
            mainImg.src = url;
            thumbs.forEach((t) => {
                const on = t.dataset.thumb === url;
                t.classList.toggle('ring-2', on);
                t.classList.toggle('ring-brand-500', on);
            });
        }

        function render() {
            // Colours: available if some in-stock variant matches (given the chosen size).
            vRoot.querySelectorAll('[data-color]').forEach((b) => {
                const c = b.dataset.color;
                const ok = variants.some((v) => v.color === c && (!selSize || v.size === selSize) && hasStock(v));
                b.disabled = !ok;
                b.classList.toggle('opacity-30', !ok);
                b.classList.toggle('ring-brand-600', c === selColor);
                b.classList.toggle('ring-transparent', c !== selColor);
                b.innerHTML = c === selColor ? '<span class="text-white drop-shadow">✓</span>' : '';
            });
            // Sizes: available if some in-stock variant matches (given the chosen colour).
            vRoot.querySelectorAll('[data-size]').forEach((b) => {
                const s = b.dataset.size;
                const ok = variants.some((v) => v.size === s && (!selColor || v.color === selColor) && hasStock(v));
                b.disabled = !ok;
                b.classList.toggle('opacity-40', !ok);
                b.classList.toggle('line-through', !ok);
                b.classList.toggle('border-brand-600', s === selSize);
                b.classList.toggle('bg-brand-50', s === selSize);
                b.classList.toggle('text-brand-700', s === selSize);
            });
            // Plain named options (no colour, no size).
            vRoot.querySelectorAll('[data-variant]').forEach((b) => {
                const v = variants.find((x) => String(x.id) === b.dataset.variant);
                const ok = !!v && hasStock(v);
                b.disabled = !ok;
                b.classList.toggle('opacity-40', !ok);
                b.classList.toggle('line-through', !ok);
                const on = String(selVariant) === b.dataset.variant;
                b.classList.toggle('border-brand-600', on);
                b.classList.toggle('bg-brand-50', on);
                b.classList.toggle('text-brand-700', on);
            });

            if (colorLabel) colorLabel.textContent = selColorName || '';

            // Swap the main photo as soon as a colour with a linked image is chosen.
            if (selColor) swapMain(colorImage(selColor));

            const v = selVariant
                ? variants.find((x) => String(x.id) === String(selVariant))
                : variants.find((x) => (!hasColors || x.color === selColor) && (!hasSizes || x.size === selSize));
            const complete = selVariant ? true : ((!hasColors || selColor) && (!hasSizes || selSize));

            if (complete && v) {
                input.value = v.id;
                if (priceEl) priceEl.textContent = new Intl.NumberFormat('fr-FR').format(basePrice + (v.delta || 0)) + ' ' + currency;
                if (v.image) swapMain(v.image);
                if (!trackStock) {
                    avail.textContent = '✓ Disponible';
                    avail.className = 'text-sm font-semibold text-green-600';
                } else {
                    avail.textContent = v.stock > 0 ? ('✓ En stock (' + v.stock + ')') : '✗ Épuisé';
                    avail.className = 'text-sm font-semibold ' + (v.stock > 0 ? 'text-green-600' : 'text-red-600');
                }
            } else {
                input.value = '';
                avail.textContent = (hasColors && hasSizes) ? 'Choisissez une couleur et une taille' : 'Choisissez une option';
                avail.className = 'text-sm text-slate-500';
            }
        }

        vRoot.querySelectorAll('[data-color]').forEach((b) =>
            b.addEventListener('click', () => { if (!b.disabled) { selColor = b.dataset.color; selColorName = b.dataset.colorName; selVariant = null; render(); } }));
        vRoot.querySelectorAll('[data-size]').forEach((b) =>
            b.addEventListener('click', () => { if (!b.disabled) { selSize = b.dataset.size; selVariant = null; render(); } }));
        vRoot.querySelectorAll('[data-variant]').forEach((b) =>
            b.addEventListener('click', () => { if (!b.disabled) { selVariant = b.dataset.variant; render(); } }));

        // A single unnamed option needs no choosing.
        const plain = vRoot.querySelectorAll('[data-variant]');
        if (plain.length === 1 && !hasColors && !hasSizes) {
            selVariant = plain[0].dataset.variant;
        }
        render();
    }

    // ── Social sharing ──────────────────────────────────────────────────
    const shareRoot = document.querySelector('[data-share-root]');
    if (shareRoot) {
        const url = shareRoot.dataset.url;
        const text = shareRoot.dataset.text;
        const title = shareRoot.dataset.title;

        const copyLink = async () => {
            try {
                await navigator.clipboard.writeText(url);
            } catch (e) {
                const ta = document.createElement('textarea');
                ta.value = url;
                document.body.appendChild(ta);
                ta.select();
                document.execCommand('copy');
                ta.remove();
            }
        };

        const nativeShare = () => navigator.share({ title, text, url });

        // Native "Share" button — only useful where the Web Share API exists.
        const nativeBtn = shareRoot.querySelector('[data-share-native]');
        if (nativeBtn) {
            if (navigator.share) {
                nativeBtn.classList.remove('hidden');
            }
            nativeBtn.addEventListener('click', () =>
                navigator.share ? nativeShare().catch(() => {}) : copyLink().then(() => toast('Lien copié ✓')));
        }

        shareRoot.querySelectorAll('[data-share]').forEach((btn) => {
            const kind = btn.dataset.share;
            if (kind === 'native') return; // handled above

            btn.addEventListener('click', async () => {
                if (kind === 'copy') {
                    await copyLink();
                    toast('Lien copié ✓');
                } else if (kind === 'app') {
                    // Instagram / TikTok have no web post-intent. On mobile the OS
                    // share sheet lets the user post to the app; on desktop we
                    // copy the link and tell them to paste it in the app.
                    if (navigator.share) {
                        nativeShare().catch(() => {});
                    } else {
                        await copyLink();
                        toast('Lien copié ✓ — collez-le dans votre publication ' + (btn.dataset.app || ''));
                    }
                }
            });
        });
    }

    // Quantity steppers
    document.querySelectorAll('[data-qty]').forEach((wrap) => {
        const input = wrap.querySelector('input');
        wrap.querySelector('[data-inc]')?.addEventListener('click', () => {
            input.value = parseInt(input.value || '1') + 1;
            input.dispatchEvent(new Event('change'));
        });
        wrap.querySelector('[data-dec]')?.addEventListener('click', () => {
            input.value = Math.max(1, parseInt(input.value || '1') - 1);
            input.dispatchEvent(new Event('change'));
        });
    });
});
