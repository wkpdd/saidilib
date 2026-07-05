import './bootstrap';

// Lightweight progressive-enhancement helpers (no framework needed).
document.addEventListener('DOMContentLoaded', () => {
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

    // ── Variant picker: colour + size with live availability ────────────
    const vRoot = document.querySelector('[data-variants]');
    if (vRoot) {
        const variants = JSON.parse(vRoot.dataset.variants || '[]');
        const basePrice = parseFloat(vRoot.dataset.basePrice || '0');
        const currency = vRoot.dataset.currency || '';
        const input = vRoot.querySelector('[data-variant-input]');
        const avail = vRoot.querySelector('[data-availability]');
        const colorLabel = vRoot.querySelector('[data-color-label]');
        const priceEl = document.querySelector('[data-price]');
        const mainImg = document.querySelector('[data-main-image]');
        const hasColors = variants.some((v) => v.color);
        const hasSizes = variants.some((v) => v.size);
        let selColor = null;
        let selSize = null;

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
                const ok = variants.some((v) => v.color === c && (!selSize || v.size === selSize) && v.stock > 0);
                b.disabled = !ok;
                b.classList.toggle('opacity-30', !ok);
                b.classList.toggle('ring-brand-600', c === selColor);
                b.classList.toggle('ring-transparent', c !== selColor);
                b.innerHTML = c === selColor ? '<span class="text-white drop-shadow">✓</span>' : '';
            });
            // Sizes: available if some in-stock variant matches (given the chosen colour).
            vRoot.querySelectorAll('[data-size]').forEach((b) => {
                const s = b.dataset.size;
                const ok = variants.some((v) => v.size === s && (!selColor || v.color === selColor) && v.stock > 0);
                b.disabled = !ok;
                b.classList.toggle('opacity-40', !ok);
                b.classList.toggle('line-through', !ok);
                b.classList.toggle('border-brand-600', s === selSize);
                b.classList.toggle('bg-brand-50', s === selSize);
                b.classList.toggle('text-brand-700', s === selSize);
            });
            if (colorLabel) colorLabel.textContent = selColor || '';

            // Swap the main photo as soon as a colour with a linked image is chosen.
            if (selColor) swapMain(colorImage(selColor));

            const v = variants.find((x) => (!hasColors || x.color === selColor) && (!hasSizes || x.size === selSize));
            const complete = (!hasColors || selColor) && (!hasSizes || selSize);

            if (complete && v) {
                input.value = v.id;
                if (priceEl) priceEl.textContent = new Intl.NumberFormat('fr-FR').format(basePrice + (v.delta || 0)) + ' ' + currency;
                if (v.image) swapMain(v.image);
                avail.textContent = v.stock > 0 ? ('✓ En stock (' + v.stock + ')') : '✗ Épuisé';
                avail.className = 'text-sm font-semibold ' + (v.stock > 0 ? 'text-green-600' : 'text-red-600');
            } else {
                input.value = '';
                avail.textContent = (hasColors && hasSizes) ? 'Choisissez une couleur et une taille' : 'Choisissez une option';
                avail.className = 'text-sm text-slate-500';
            }
        }

        vRoot.querySelectorAll('[data-color]').forEach((b) =>
            b.addEventListener('click', () => { if (!b.disabled) { selColor = b.dataset.color; render(); } }));
        vRoot.querySelectorAll('[data-size]').forEach((b) =>
            b.addEventListener('click', () => { if (!b.disabled) { selSize = b.dataset.size; render(); } }));
        render();
    }

    // ── Social sharing ──────────────────────────────────────────────────
    const shareRoot = document.querySelector('[data-share-root]');
    if (shareRoot) {
        const url = shareRoot.dataset.url;
        const text = shareRoot.dataset.text;
        const title = shareRoot.dataset.title;

        const toast = (msg) => {
            const el = document.createElement('div');
            el.textContent = msg;
            el.className = 'fixed inset-x-0 bottom-6 z-50 mx-auto w-fit max-w-[90%] rounded-xl bg-ink-900 px-4 py-2.5 text-center text-sm font-medium text-white shadow-card';
            document.body.appendChild(el);
            setTimeout(() => el.remove(), 3200);
        };

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
