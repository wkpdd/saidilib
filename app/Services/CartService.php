<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\Session;

/**
 * Session-backed shopping cart. Keys items by product + variant so the same
 * product in two sizes stays as two lines.
 */
class CartService
{
    private const KEY = 'cart';

    public function items(): array
    {
        return Session::get(self::KEY, []);
    }

    public function add(Product $product, ?ProductVariant $variant, int $qty = 1, ?float $unitPrice = null): void
    {
        $qty = max(1, $qty);
        $key = $this->lineKey($product->id, $variant?->id);
        $cart = $this->items();

        if (isset($cart[$key])) {
            $cart[$key]['qty'] += $qty;
        } else {
            // Effective price (e.g. wholesale tier) when provided; else retail.
            $unit = $unitPrice ?? ($variant ? (float) $variant->price : (float) $product->price);
            $cart[$key] = [
                'product_id'  => $product->id,
                'variant_id'  => $variant?->id,
                'name'        => $product->name,
                'variant'     => $variant?->label,
                'slug'        => $product->slug,
                'price'       => $unit,
                'qty'         => $qty,
                'image'       => $variant && $variant->image ? $variant->image->url : $product->main_image_url,
            ];
        }

        Session::put(self::KEY, $cart);
    }

    public function update(string $key, int $qty): void
    {
        $cart = $this->items();
        if (! isset($cart[$key])) {
            return;
        }
        if ($qty <= 0) {
            unset($cart[$key]);
        } else {
            $cart[$key]['qty'] = $qty;
        }
        Session::put(self::KEY, $cart);
    }

    public function remove(string $key): void
    {
        $cart = $this->items();
        unset($cart[$key]);
        Session::put(self::KEY, $cart);
    }

    public function clear(): void
    {
        Session::forget(self::KEY);
    }

    public function count(): int
    {
        return array_sum(array_column($this->items(), 'qty'));
    }

    public function subtotal(): float
    {
        $total = 0;
        foreach ($this->items() as $line) {
            $total += $line['price'] * $line['qty'];
        }

        return $total;
    }

    private function lineKey(int $productId, ?int $variantId): string
    {
        return $productId . ':' . ($variantId ?? 0);
    }
}
