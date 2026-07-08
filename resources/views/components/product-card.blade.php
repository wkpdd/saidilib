@props(['product'])
@php $hasVariants = $product->variants->isNotEmpty(); @endphp
<div class="group card flex flex-col overflow-hidden transition hover:-translate-y-1 hover:shadow-card">
    <a href="{{ route('product', $product->slug) }}" class="block">
        <div class="relative aspect-square overflow-hidden bg-slate-100">
            <img src="{{ $product->card_image_url }}"
                 @if ($product->card_srcset) srcset="{{ $product->card_srcset }}" sizes="(min-width: 1024px) 25vw, (min-width: 640px) 33vw, 50vw" @endif
                 alt="{{ $product->name }}" width="300" height="300" loading="lazy" decoding="async"
                 class="h-full w-full object-cover transition duration-500 group-hover:scale-105">
            <div class="absolute start-2 top-2 flex flex-col gap-1">
                @if ($product->is_new)
                    <span class="badge bg-brand-600 text-white">{{ __('shop.new') }}</span>
                @endif
                @if ($product->has_tier_price)
                    <span class="badge bg-brand-700 text-white">💼 {{ __('shop.wholesale') }}</span>
                @elseif ($product->on_sale)
                    <span class="badge bg-accent text-white">-{{ $product->discount_percent }}%</span>
                @endif
            </div>
        </div>
        <div class="p-3.5 pb-2">
            @if ($product->category)
                <span class="text-[11px] font-semibold uppercase tracking-wide text-brand-600">{{ $product->category->name }}</span>
            @endif
            <h3 class="mt-0.5 line-clamp-2 min-h-[2.5rem] text-sm font-semibold text-ink-900">{{ $product->name }}</h3>
            <div class="mt-2 flex items-end gap-2">
                <span class="text-base font-bold text-ink-900">@money($product->current_price)</span>
                @if ($product->has_tier_price)
                    <span class="text-xs text-slate-400 line-through">@money($product->price)</span>
                @elseif ($product->on_sale)
                    <span class="text-xs text-slate-400 line-through">@money($product->compare_at_price)</span>
                @endif
            </div>
        </div>
    </a>

    {{-- Quick add-to-cart --}}
    <div class="mt-auto p-3.5 pt-0">
        @if ($hasVariants)
            <a href="{{ route('product', $product->slug) }}"
               class="btn-primary w-full justify-center text-sm">{{ __('shop.choose_options') }}</a>
        @else
            <form action="{{ route('cart.add') }}" method="post" class="flex items-center gap-2">
                @csrf
                <input type="hidden" name="product_id" value="{{ $product->id }}">
                <div data-qty class="inline-flex shrink-0 items-center rounded-xl ring-1 ring-slate-200">
                    <button type="button" data-dec class="grid h-9 w-8 place-items-center text-lg text-slate-500 hover:bg-slate-50">−</button>
                    <input type="number" name="qty" value="1" min="1" class="h-9 w-9 border-0 p-0 text-center text-sm focus:ring-0">
                    <button type="button" data-inc class="grid h-9 w-8 place-items-center text-lg text-slate-500 hover:bg-slate-50">+</button>
                </div>
                <button type="submit" class="btn-primary flex-1 justify-center text-sm">🛒 {{ __('shop.add') }}</button>
            </form>
        @endif
    </div>
</div>
