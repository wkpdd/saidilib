<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\CartService;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(private CartService $cart) {}

    public function index()
    {
        return view('storefront.cart', [
            'items'    => $this->cart->items(),
            'subtotal' => $this->cart->subtotal(),
        ]);
    }

    public function add(Request $request)
    {
        $data = $request->validate([
            'product_id' => 'required|exists:products,id',
            'variant_id' => 'nullable|exists:product_variants,id',
            'qty'        => 'nullable|integer|min:1',
        ]);

        $product = Product::active()->findOrFail($data['product_id']);
        $variant = isset($data['variant_id'])
            ? ProductVariant::where('product_id', $product->id)->find($data['variant_id'])
            : null;

        $qty = (int) ($data['qty'] ?? 1);

        // If the product has variants, one must be chosen. Stock is only
        // enforced per-variant when the merchant actually tracks stock —
        // otherwise a variant left at stock=0 by the admin would block sales.
        if ($product->variants()->exists()) {
            if (! $variant) {
                return back()->with('error', __('shop.choose_option'));
            }
            if ($product->track_stock && (int) $variant->stock < $qty) {
                return back()->with('error', __('shop.variant_out_of_stock'));
            }
        }

        // Apply the logged-in client's pricing tier (retail / wholesale / super).
        $client = \Illuminate\Support\Facades\Auth::guard('client')->user();
        $unitPrice = $product->priceForTier($client?->type)
            + ($variant ? (float) $variant->price_delta : 0);

        $this->cart->add($product, $variant, $qty, $unitPrice);

        if ($request->expectsJson()) {
            return response()->json(['count' => $this->cart->count(), 'message' => __('shop.added_to_cart')]);
        }

        return redirect()->route('cart.index')->with('success', __('shop.added_to_cart'));
    }

    public function update(Request $request)
    {
        $request->validate(['key' => 'required|string', 'qty' => 'required|integer|min:0']);
        $this->cart->update($request->key, (int) $request->qty);

        return back();
    }

    public function remove(Request $request)
    {
        $request->validate(['key' => 'required|string']);
        $this->cart->remove($request->key);

        return back()->with('success', __('shop.item_removed'));
    }

    public function clear()
    {
        $this->cart->clear();

        return back();
    }
}
