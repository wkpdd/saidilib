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
