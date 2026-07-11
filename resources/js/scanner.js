/**
 * Barcode / QR scanner for the admin panel — used to find/create products by
 * SKU without typing. Loaded only on the pages that need it (product form,
 * stock-receipt form), never on the storefront.
 *
 * Uses the native BarcodeDetector API (Shape Detection API) — zero extra
 * bytes downloaded, decodes EAN-13/EAN-8/UPC/Code128/QR from either a live
 * camera stream or an uploaded photo. Supported in Chrome/Edge (desktop and
 * Android); not in Firefox/Safari, where we show a clear message rather than
 * silently failing.
 */
(function () {
    const FORMATS = ['ean_13', 'ean_8', 'upc_a', 'upc_e', 'code_128', 'code_39', 'qr_code'];

    function supported() {
        return 'BarcodeDetector' in window;
    }

    /**
     * Open the scanner. Calls onDetect(code) with the decoded string once a
     * barcode/QR is found (via camera or uploaded photo), then closes itself.
     */
    function open(onDetect) {
        const overlay = document.createElement('div');
        overlay.className = 'fixed inset-0 z-[100] flex flex-col items-center justify-center bg-black/90 p-4';
        overlay.innerHTML = `
            <div class="w-full max-w-sm rounded-2xl bg-white p-4">
                <div class="mb-3 flex items-center justify-between">
                    <h3 class="font-semibold text-ink-900">📷 Scanner un code-barres / QR</h3>
                    <button type="button" data-close class="grid h-8 w-8 place-items-center rounded-lg hover:bg-slate-100">✕</button>
                </div>
                <div data-camera-wrap class="relative hidden overflow-hidden rounded-xl bg-black">
                    <video data-video class="aspect-square w-full object-cover" playsinline muted></video>
                    <div class="pointer-events-none absolute inset-8 rounded-xl border-2 border-white/70"></div>
                </div>
                <p data-status class="mt-3 text-center text-sm text-slate-500">Initialisation de la caméra…</p>
                <label class="btn-ghost mt-3 block w-full cursor-pointer text-center">
                    📁 Utiliser une photo
                    <input type="file" accept="image/*" data-file class="hidden">
                </label>
            </div>`;
        document.body.appendChild(overlay);

        const status = overlay.querySelector('[data-status]');
        const camWrap = overlay.querySelector('[data-camera-wrap]');
        const video = overlay.querySelector('[data-video]');
        const fileInput = overlay.querySelector('[data-file]');
        let stream = null;
        let raf = null;
        let closed = false;

        function close() {
            if (closed) return;
            closed = true;
            if (raf) cancelAnimationFrame(raf);
            if (stream) stream.getTracks().forEach((t) => t.stop());
            overlay.remove();
        }
        overlay.querySelector('[data-close]').addEventListener('click', close);

        if (!supported()) {
            status.textContent = "Ce navigateur ne supporte pas le scanner. Utilisez Chrome ou Edge (Android ou ordinateur).";
            fileInput.closest('label').classList.add('hidden');
            return { close };
        }

        const detector = new BarcodeDetector({ formats: FORMATS });

        function found(code) {
            close();
            onDetect(code);
        }

        // Live camera scanning.
        navigator.mediaDevices?.getUserMedia({ video: { facingMode: 'environment' } })
            .then((s) => {
                if (closed) { s.getTracks().forEach((t) => t.stop()); return; }
                stream = s;
                video.srcObject = s;
                video.play();
                camWrap.classList.remove('hidden');
                status.textContent = 'Visez le code-barres ou le QR code…';

                const loop = async () => {
                    if (closed) return;
                    try {
                        const codes = await detector.detect(video);
                        if (codes.length) { found(codes[0].rawValue); return; }
                    } catch (e) { /* transient decode errors are normal, keep looping */ }
                    raf = requestAnimationFrame(loop);
                };
                loop();
            })
            .catch(() => {
                status.textContent = "Caméra indisponible — utilisez « Utiliser une photo » ci-dessous.";
            });

        // Fallback / alternative: decode a chosen photo.
        fileInput.addEventListener('change', async () => {
            const file = fileInput.files[0];
            if (!file) return;
            status.textContent = 'Analyse de la photo…';
            try {
                const bitmap = await createImageBitmap(file);
                const codes = await detector.detect(bitmap);
                if (codes.length) { found(codes[0].rawValue); return; }
                status.textContent = 'Aucun code détecté sur cette photo. Réessayez.';
            } catch (e) {
                status.textContent = "Impossible de lire cette image.";
            }
        });

        return { close };
    }

    window.SaidiScanner = { open, supported };
})();
