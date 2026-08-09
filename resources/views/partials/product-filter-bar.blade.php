{{--
    Toolbar above a product grid: result count, sort, the removable chips for
    whatever is applied, and — on mobile — the filter panel itself, folded away.

    @param \App\Support\ProductFilter $filter
    @param \Illuminate\Pagination\LengthAwarePaginator $products
--}}
<div class="mb-4 space-y-3">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-slate-500">
            <span class="font-semibold text-ink-900">{{ $products->total() }}</span> {{ __('shop.results') }}
        </p>

        <div class="flex items-center gap-2">
            <form method="get" class="flex items-center gap-2 text-sm">
                @foreach ($filter->hiddenExcept(['sort']) as $field)
                    <input type="hidden" name="{{ $field['name'] }}" value="{{ $field['value'] }}">
                @endforeach
                <label for="sort" class="hidden text-slate-500 sm:block">{{ __('shop.sort') }}</label>
                <select id="sort" name="sort" onchange="this.form.submit()" class="input w-auto py-1.5">
                    @foreach (\App\Support\ProductFilter::SORTS as $value => $labelKey)
                        <option value="{{ $value }}" @selected(request('sort') === $value)>{{ __($labelKey) }}</option>
                    @endforeach
                </select>
                <noscript><button class="btn-ghost py-1.5">OK</button></noscript>
            </form>
        </div>
    </div>

    @if ($filter->isActive())
        <div class="flex flex-wrap items-center gap-2">
            @foreach ($filter->chips() as $chip)
                <a href="{{ $chip['url'] }}"
                   class="group inline-flex items-center gap-1.5 rounded-full bg-brand-50 py-1 pe-2 ps-3 text-xs font-medium text-brand-700 ring-1 ring-brand-100 hover:bg-brand-100">
                    {{ $chip['label'] }}
                    <span class="grid h-4 w-4 place-items-center rounded-full bg-brand-600/10 text-brand-700 group-hover:bg-brand-600 group-hover:text-white">×</span>
                </a>
            @endforeach
            <a href="{{ $filter->resetUrl() }}" class="text-xs font-semibold text-slate-500 hover:text-red-600 hover:underline">
                {{ __('shop.filter_reset') }}
            </a>
        </div>
    @endif
</div>

{{-- Mobile: the same panel, folded away. Plain <details>, so it works with no JS. --}}
<details class="card mb-4 lg:hidden" @if ($filter->isActive()) open @endif>
    <summary class="flex cursor-pointer list-none items-center justify-between gap-2 px-4 py-3 font-semibold">
        <span class="flex items-center gap-2">
            <svg class="h-4 w-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" d="M3 6h18M6 12h12M10 18h4"/>
            </svg>
            {{ __('shop.filters') }}
            @if ($filter->activeCount())
                <span class="rounded-full bg-brand-600 px-1.5 text-xs font-bold text-white">{{ $filter->activeCount() }}</span>
            @endif
        </span>
        <span class="text-slate-400">▾</span>
    </summary>
    <div class="border-t border-slate-100 p-4">
        {{-- The category list lives in the desktop sidebar; mobile gets it here. --}}
        @isset($categories)
            <div class="mb-5">
                <label class="label">{{ __('shop.categories') }}</label>
                <div class="flex flex-wrap gap-1.5">
                    <a href="{{ route('catalog') }}"
                       class="rounded-full px-2.5 py-1 text-xs font-medium ring-1 {{ ! request('category') ? 'bg-brand-600 text-white ring-brand-600' : 'bg-white text-slate-600 ring-slate-200' }}">
                        {{ __('shop.all_products') }}
                    </a>
                    @foreach ($categories as $cat)
                        <a href="{{ route('category', $cat->slug) }}"
                           class="rounded-full bg-white px-2.5 py-1 text-xs font-medium text-slate-600 ring-1 ring-slate-200">
                            {{ $cat->icon }} {{ $cat->name }}
                        </a>
                    @endforeach
                </div>
            </div>
        @endisset
        @include('partials.product-filters', ['filter' => $filter])
    </div>
</details>
