@props(['product'])
<a href="{{ route('product', $product->slug) }}"
   class="group card overflow-hidden transition hover:-translate-y-1 hover:shadow-card">
    <div class="relative aspect-square overflow-hidden bg-slate-100">
        <img src="{{ $product->main_image_url }}" alt="{{ $product->name }}" loading="lazy"
             class="h-full w-full object-cover transition duration-500 group-hover:scale-105">
        <div class="absolute start-2 top-2 flex flex-col gap-1">
            @if ($product->is_new)
                <span class="badge bg-brand-600 text-white">{{ __('shop.new') }}</span>
            @endif
            @if ($product->on_sale)
                <span class="badge bg-accent text-white">-{{ $product->discount_percent }}%</span>
            @endif
        </div>
    </div>
    <div class="p-3.5">
        @if ($product->category)
            <span class="text-[11px] font-semibold uppercase tracking-wide text-brand-600">{{ $product->category->name }}</span>
        @endif
        <h3 class="mt-0.5 line-clamp-2 min-h-[2.5rem] text-sm font-semibold text-ink-900">{{ $product->name }}</h3>
        <div class="mt-2 flex items-end gap-2">
            <span class="text-base font-bold text-ink-900">@money($product->price)</span>
            @if ($product->on_sale)
                <span class="text-xs text-slate-400 line-through">@money($product->compare_at_price)</span>
            @endif
        </div>
    </div>
</a>
