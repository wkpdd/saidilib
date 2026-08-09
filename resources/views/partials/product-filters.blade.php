{{--
    Product filter panel — one plain GET form, no JS required, so a single
    request applies everything on a weak connection.

    @param \App\Support\ProductFilter $filter
--}}
@php
    $brands  = $filter->brands();
    $bounds  = $filter->priceBounds();
    $ranges  = collect([[null, 500], [500, 1500], [1500, 5000], [5000, null]])
        ->filter(fn ($r) => ($r[0] === null || $r[0] < $bounds['high']) && ($r[1] === null || $r[1] > $bounds['low']));
@endphp

<form method="get" action="{{ url()->current() }}" class="space-y-5">
    {{-- Carry keys the panel doesn't own (e.g. an outer context) --}}
    @foreach ($filter->hiddenExcept(\App\Support\ProductFilter::KEYS) as $field)
        <input type="hidden" name="{{ $field['name'] }}" value="{{ $field['value'] }}">
    @endforeach
    @if (request('sort'))
        <input type="hidden" name="sort" value="{{ request('sort') }}">
    @endif
    @if (request('category'))
        <input type="hidden" name="category" value="{{ request('category') }}">
    @endif

    {{-- Search within results --}}
    <div>
        <label class="label">{{ __('shop.filter_search') }}</label>
        <input name="q" value="{{ request('q') }}" placeholder="{{ __('shop.search') }}" class="input">
    </div>

    {{-- Price --}}
    <div>
        <label class="label">{{ __('shop.price') }} (DA)</label>
        <div class="flex items-center gap-2">
            <input type="number" name="min" inputmode="numeric" min="0" value="{{ request('min') }}"
                   placeholder="{{ $bounds['low'] }}" class="input">
            <span class="text-slate-400">—</span>
            <input type="number" name="max" inputmode="numeric" min="0" value="{{ request('max') }}"
                   placeholder="{{ $bounds['high'] }}" class="input">
        </div>
        @if ($ranges->count() > 1)
            <div class="mt-2 flex flex-wrap gap-1.5">
                @foreach ($ranges as [$low, $high])
                    @php
                        $on = (string) request('min') === (string) $low && (string) request('max') === (string) $high;
                        $label = $low === null ? '< ' . $high : ($high === null ? '> ' . $low : $low . ' – ' . $high);
                    @endphp
                    <a href="{{ $filter->urlWith(['min' => $low, 'max' => $high]) }}"
                       class="rounded-full px-2.5 py-1 text-xs font-medium ring-1 transition {{ $on ? 'bg-brand-600 text-white ring-brand-600' : 'bg-white text-slate-600 ring-slate-200 hover:bg-slate-50' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Quick toggles --}}
    <div>
        <label class="label">{{ __('shop.filter_options') }}</label>
        <div class="space-y-2 text-sm">
            @foreach (\App\Support\ProductFilter::TOGGLES as $key => $labelKey)
                <label class="flex cursor-pointer items-center gap-2 text-ink-700">
                    <input type="checkbox" name="{{ $key }}" value="1" @checked(request()->boolean($key))
                           class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                    {{ __($labelKey) }}
                </label>
            @endforeach
        </div>
    </div>

    {{-- Brands --}}
    @if ($brands->count() > 1)
        <div>
            <label class="label">{{ __('shop.brand') }}</label>
            <div class="max-h-56 space-y-2 overflow-y-auto pe-1 text-sm">
                @foreach ($brands as $brand)
                    <label class="flex cursor-pointer items-center justify-between gap-2 text-ink-700">
                        <span class="flex min-w-0 items-center gap-2">
                            <input type="checkbox" name="brand[]" value="{{ $brand->brand }}"
                                   @checked(in_array($brand->brand, $filter->selectedBrands(), true))
                                   class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                            <span class="truncate">{{ $brand->brand }}</span>
                        </span>
                        <span class="shrink-0 text-xs text-slate-400">{{ $brand->total }}</span>
                    </label>
                @endforeach
            </div>
        </div>
    @endif

    <div class="flex gap-2">
        <button class="btn-primary flex-1">{{ __('shop.filter') }}</button>
        @if ($filter->isActive())
            <a href="{{ $filter->resetUrl() }}" class="btn-ghost">{{ __('shop.filter_reset') }}</a>
        @endif
    </div>
</form>
