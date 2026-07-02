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

        $this->cart->add($product, $variant, (int) ($data['qty'] ?? 1));

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
