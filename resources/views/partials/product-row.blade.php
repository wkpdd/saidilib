@if (($products ?? collect())->isNotEmpty())
<section class="container-x py-8">
    <div class="mb-5 flex items-end justify-between">
        <h2 class="font-display text-2xl font-bold">{{ $title }}</h2>
        <a href="{{ route('catalog') }}" class="text-sm font-semibold text-brand-700 hover:underline">{{ __('shop.view_all') }} →</a>
    </div>
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
        @foreach ($products as $product)
            <x-product-card :product="$product" />
        @endforeach
    </div>
</section>
@endif
