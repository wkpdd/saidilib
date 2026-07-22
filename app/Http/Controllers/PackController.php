<?php

namespace App\Http\Controllers;

use App\Models\Pack;
use App\Services\CartService;

class PackController extends Controller
{
    public function __construct(private CartService $cart) {}

    public function show(string $slug)
    {
        $pack = Pack::active()->where('slug', $slug)->with('items.product.images', 'items.variant')->firstOrFail();

        return view('storefront.pack', compact('pack'));
    }

    /**
     * Add the whole pack to the cart in one tap. When a promo price is set,
     * the discount is spread proportionally over the line unit prices (the
     * last line absorbs the rounding) so the cart total equals the promo.
     */
    public function addToCart(string $slug)
    {
        $pack = Pack::active()->where('slug', $slug)->with('items.product', 'items.variant')->firstOrFail();
        $items = $pack->items->filter(fn ($i) => $i->product && $i->product->is_active)->values();

        if ($items->isEmpty()) {
            return redirect()->route('home');
        }

        $sum = $pack->items_total;
        $target = $pack->effective_price;
        $factor = $sum > 0 ? $target / $sum : 1.0;

        $charged = 0.0;
        foreach ($items as $idx => $item) {
            $base = (float) $item->product->price + (float) ($item->variant->price_delta ?? 0);
            if ($idx < $items->count() - 1) {
                $unit = round($base * $factor, 2);
            } else {
                // Last line absorbs rounding so the total lands exactly on target.
                $remaining = $target - $charged;
                $unit = max(0, round($remaining / $item->quantity, 2));
            }
            $charged += $unit * $item->quantity;

            $this->cart->add($item->product, $item->variant, $item->quantity, $unit);
        }

        return redirect()->route('cart.index')->with('success', __('shop.pack_added'));
    }
}
