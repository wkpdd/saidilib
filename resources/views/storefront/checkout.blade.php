@extends('layouts.app')
@section('title', __('shop.checkout'))

@section('content')
<div class="container-x py-8">
    <h1 class="mb-6 font-display text-2xl font-bold">{{ __('shop.checkout') }}</h1>

    <form action="{{ route('checkout.store') }}" method="post" class="grid gap-6 lg:grid-cols-[1fr_380px]">
        @csrf
        {{-- Customer info --}}
        <div class="space-y-5">
            <div class="card p-6">
                <h2 class="mb-4 font-semibold">{{ __('shop.your_info') }}</h2>
                @if ($errors->any())
                    <div class="mb-4 rounded-xl bg-red-50 p-3 text-sm text-red-700">
                        <ul class="list-disc ps-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                    </div>
                @endif
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="label">{{ __('shop.full_name') }} *</label>
                        <input name="customer_name" value="{{ old('customer_name') }}" required class="input">
                    </div>
                    <div>
                        <label class="label">{{ __('shop.phone') }} *</label>
                        <input name="phone" value="{{ old('phone') }}" required inputmode="tel" class="input" placeholder="05 / 06 / 07 ...">
                    </div>
                    <div>
                        <label class="label">{{ __('shop.phone2') }}</label>
                        <input name="phone2" value="{{ old('phone2') }}" inputmode="tel" class="input">
                    </div>
                </div>
            </div>

            <div class="card p-6">
                <h2 class="mb-4 font-semibold">{{ __('shop.delivery') }}</h2>

                {{-- Delivery type --}}
                <div class="grid grid-cols-2 gap-3">
                    <label class="cursor-pointer">
                        <input type="radio" name="delivery_type" value="home" class="peer sr-only" id="dt_home" checked>
                        <div class="rounded-xl border border-slate-200 p-4 text-center peer-checked:border-brand-600 peer-checked:bg-brand-50">
                            <div class="text-2xl">🏠</div>
                            <div class="mt-1 text-sm font-semibold">{{ __('shop.home_delivery') }}</div>
                        </div>
                    </label>
                    <label class="cursor-pointer">
                        <input type="radio" name="delivery_type" value="stopdesk" class="peer sr-only" id="dt_desk">
                        <div class="rounded-xl border border-slate-200 p-4 text-center peer-checked:border-brand-600 peer-checked:bg-brand-50">
                            <div class="text-2xl">🏢</div>
                            <div class="mt-1 text-sm font-semibold">{{ __('shop.stopdesk') }}</div>
                        </div>
                    </label>
                </div>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="label">{{ __('shop.wilaya') }} *</label>
                        <select name="wilaya_id" id="wilaya" required class="input">
                            <option value="">{{ __('shop.select_wilaya') }}</option>
                            @foreach ($wilayas as $w)
                                <option value="{{ $w->id }}" data-home="{{ $w->home_fee }}" data-desk="{{ $w->stopdesk_fee }}"
                                    @selected(old('wilaya_id')==$w->id)>{{ $w->label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label">{{ __('shop.commune') }} <span id="communeReq">*</span></label>
                        {{-- Two fields, one submitted: the select is used as soon as we
                             have the carrier's list, the text input is the fallback
                             (no JS, or carrier API unreachable). --}}
                        <select name="commune" id="communeSelect" class="input hidden" disabled>
                            <option value="">{{ __('shop.select_commune') }}</option>
                        </select>
                        <input name="commune" id="commune" value="{{ old('commune') }}" class="input"
                               autocomplete="address-level2">
                    </div>
                    <div class="sm:col-span-2" id="addressWrap">
                        <label class="label">{{ __('shop.address') }}</label>
                        <input name="address" value="{{ old('address') }}" class="input" placeholder="Rue, quartier...">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="label">{{ __('shop.notes') }}</label>
                        <textarea name="notes" rows="2" class="input">{{ old('notes') }}</textarea>
                    </div>
                </div>
            </div>

            <div class="card flex items-center gap-3 p-6">
                <span class="grid h-11 w-11 place-items-center rounded-xl bg-green-50 text-2xl">💵</span>
                <div>
                    <p class="font-semibold">{{ __('shop.payment_method') }}</p>
                    <p class="text-sm text-slate-500">{{ __('shop.cod_only') }}</p>
                </div>
                <span class="badge bg-green-50 text-green-700 ms-auto">✓</span>
            </div>
        </div>

        {{-- Summary --}}
        <div class="h-fit card p-5 lg:sticky lg:top-28">
            <h2 class="mb-4 font-semibold">{{ __('shop.order_summary') }}</h2>
            <div class="max-h-64 space-y-3 overflow-y-auto">
                @foreach ($items as $line)
                    <div class="flex items-center gap-3">
                        <img src="{{ $line['image'] }}" class="h-12 w-12 rounded-lg object-cover ring-1 ring-slate-100" alt="">
                        <div class="min-w-0 flex-1 text-sm">
                            <p class="truncate font-medium">{{ $line['name'] }}</p>
                            <p class="text-xs text-slate-500">{{ $line['qty'] }} × @money($line['price'])</p>
                        </div>
                        <span class="text-sm font-semibold">@money($line['price'] * $line['qty'])</span>
                    </div>
                @endforeach
            </div>
            <div class="mt-4 space-y-1.5 border-t border-slate-100 pt-4 text-sm">
                <div class="flex justify-between"><span class="text-slate-500">{{ __('shop.subtotal') }}</span><span class="font-semibold" id="sumSub" data-sub="{{ $subtotal }}">@money($subtotal)</span></div>
                @if (($savings ?? 0) > 0)
                    <div class="flex justify-between"><span class="text-accent">💼 {{ __('shop.qty_savings') }}</span><span class="font-semibold text-accent">−@money($savings)</span></div>
                @endif
                <div class="flex justify-between">
                    <span class="text-slate-500">{{ __('shop.delivery') }}</span>
                    <span class="font-semibold {{ $freeShipping ? 'text-green-600' : '' }}" id="sumFee">{{ $freeShipping ? __('shop.free_shipping') : '—' }}</span>
                </div>
                @if ($freeShipping)
                    <p class="rounded-lg bg-green-50 px-3 py-2 text-xs font-medium text-green-700">🚚 {{ __('shop.free_shipping_applied') }}</p>
                @endif
                <div class="flex justify-between border-t border-slate-100 pt-2 text-lg font-bold"><span>{{ __('shop.total') }}</span><span class="text-brand-700" id="sumTotal">@money($subtotal)</span></div>
            </div>
            <button type="submit" class="btn-accent mt-5 w-full">{{ __('shop.place_order') }}</button>
            <p class="mt-2 text-center text-xs text-slate-400">{{ __('shop.cod_only') }}</p>
            <p class="mt-3 border-t border-slate-100 pt-3 text-center text-[11px] text-slate-400">
                🔒 {{ __('shop.secure_order') }} · {{ __('shop.developed_by') }}
                <a href="https://h47.io" target="_blank" rel="noopener" class="font-semibold underline-offset-2 hover:text-brand-700 hover:underline">h47.io</a>
            </p>
        </div>
    </form>
</div>

@push('scripts')
<script>
    const currency = @json(\App\Models\Setting::get('currency','DA'));
    const fmt = (n) => new Intl.NumberFormat('fr-FR').format(n) + ' ' + currency;
    const sub = parseFloat(document.getElementById('sumSub').dataset.sub);
    const wilaya = document.getElementById('wilaya');
    const feeEl = document.getElementById('sumFee');
    const totalEl = document.getElementById('sumTotal');
    const addressWrap = document.getElementById('addressWrap');
    // Server-side truth: every product in the cart has a running free-delivery
    // offer, so no wilaya fee applies whatever the customer picks.
    const freeShipping = @json((bool) $freeShipping);
    const freeLabel = @json(__('shop.free_shipping'));

    function deliveryType() {
        return document.querySelector('input[name=delivery_type]:checked').value;
    }
    function recalc() {
        if (freeShipping) { feeEl.textContent = freeLabel; totalEl.textContent = fmt(sub); return; }
        const opt = wilaya.selectedOptions[0];
        if (!opt || !opt.value) { feeEl.textContent = '—'; totalEl.textContent = fmt(sub); return; }
        const fee = parseFloat(deliveryType() === 'stopdesk' ? opt.dataset.desk : opt.dataset.home) || 0;
        feeEl.textContent = fmt(fee);
        totalEl.textContent = fmt(sub + fee);
    }
    // The communes the carrier serves in the chosen wilaya. One small request
    // per wilaya change; if it fails the free-text field stays in place.
    const communeSelect = document.getElementById('communeSelect');
    const communeInput = document.getElementById('commune');
    const communeReq = document.getElementById('communeReq');
    const communeUrl = @json(route('checkout.communes'));
    const communePlaceholder = communeSelect.options[0].textContent;

    function useFreeText() {
        communeSelect.classList.add('hidden');
        communeSelect.disabled = true;              // disabled → not submitted
        communeInput.classList.remove('hidden');
        communeInput.disabled = false;
    }

    function useList(names) {
        const chosen = communeInput.value.trim().toLowerCase();
        communeSelect.innerHTML = '<option value="">' + communePlaceholder + '</option>'
            + names.map((c) => '<option value="' + c.replace(/"/g, '&quot;') + '"'
                + (c.toLowerCase() === chosen ? ' selected' : '') + '>' + c + '</option>').join('');
        communeSelect.classList.remove('hidden');
        communeSelect.disabled = false;
        communeInput.classList.add('hidden');
        communeInput.disabled = true;
    }

    function loadCommunes() {
        if (!wilaya.value) { useFreeText(); return; }
        fetch(communeUrl + '?wilaya_id=' + encodeURIComponent(wilaya.value), { headers: { Accept: 'application/json' } })
            .then((r) => r.ok ? r.json() : { communes: [] })
            .then((d) => (d.communes && d.communes.length) ? useList(d.communes) : useFreeText())
            .catch(useFreeText);
    }

    wilaya.addEventListener('change', () => { recalc(); loadCommunes(); });
    document.querySelectorAll('input[name=delivery_type]').forEach(r => r.addEventListener('change', () => {
        const home = deliveryType() !== 'stopdesk';
        addressWrap.style.display = home ? '' : 'none';
        communeReq.style.display = home ? '' : 'none';   // stop desk: commune optional
        recalc();
    }));
    recalc();
    loadCommunes();
</script>
@endpush
@endsection
