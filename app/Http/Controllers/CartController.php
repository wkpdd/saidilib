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
            'groups'   => $this->cart->groups(),
            'items'    => $this->cart->lines(),
            'subtotal' => $this->cart->subtotal(),
            'savings'  => $this->cart->quantitySavings(),
        ]);
    }

    public function add(Request $request)
    {
        $data = $request->validate([
            'product_id' => 'required|exists:products,id',
            'variant_id' => 'nullable|exists:product_variants,id',
            'qty'        => 'nullable|integer|min:1',
            'buy_now'    => 'nullable|boolean',
        ]);

        $product = Product::active()->findOrFail($data['product_id']);
        $variant = isset($data['variant_id'])
            ? ProductVariant::where('product_id', $product->id)->find($data['variant_id'])
            : null;

        $qty = (int) ($data['qty'] ?? 1);

        // Adding from a listing happens over fetch() so the shopper never
        // leaves the page — errors have to come back as JSON there, not as a
        // redirect they'd never see.
        $fail = function (string $message) use ($request) {
            return $request->expectsJson()
                ? response()->json(['ok' => false, 'message' => $message], 422)
                : back()->with('error', $message);
        };

        // If the product has variants, one must be chosen. Stock is only
        // enforced per-variant when the merchant actually tracks stock —
        // otherwise a variant left at stock=0 by the admin would block sales.
        if ($product->variants()->exists()) {
            if (! $variant) {
                return $fail(__('shop.choose_option'));
            }
            if ($product->track_stock && $variant->stock !== null && (int) $variant->stock < $qty) {
                return $fail(__('shop.variant_out_of_stock'));
            }
        }

        // Apply the logged-in client's pricing tier (retail / wholesale / super).
        $client = \Illuminate\Support\Facades\Auth::guard('client')->user();
        $unitPrice = $product->priceForTier($client?->type)
            + ($variant ? (float) $variant->price_delta : 0);

        $this->cart->add($product, $variant, $qty, $unitPrice);

        // "⚡ Commander maintenant" skips the cart entirely.
        if (! empty($data['buy_now'])) {
            return redirect()->route('checkout.index');
        }

        if ($request->expectsJson()) {
            return response()->json([
                'ok'      => true,
                'count'   => $this->cart->count(),
                'message' => __('shop.added_to_cart'),
            ]);
        }

        // No-JS fallback: the classic redirect to the cart.
        return redirect()->route('cart.index')->with('success', __('shop.added_to_cart'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'key'     => 'nullable|string',
            'pack_id' => 'nullable|integer',
            'qty'     => 'required|integer|min:0|max:999',
        ]);

        // A pack moves as one product: N packs, not N of one article.
        if ($request->filled('pack_id')) {
            $this->cart->updatePack((int) $request->pack_id, (int) $request->qty);

            return back();
        }

        $request->validate(['key' => 'required|string']);

        // Refused when the line belongs to a pack — its unit price is the
        // pack's, and must not be bought a piece at a time.
        if (! $this->cart->update($request->key, (int) $request->qty)) {
            return back()->with('error', __('shop.pack_locked'));
        }

        return back();
    }

    public function remove(Request $request)
    {
        $request->validate(['key' => 'nullable|string', 'pack_id' => 'nullable|integer']);

        if ($request->filled('pack_id')) {
            $this->cart->removePack((int) $request->pack_id);

            return back()->with('success', __('shop.pack_removed'));
        }

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
