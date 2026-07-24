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

    /**
     * Cart lines with quantity breaks resolved.
     *
     * The session only ever stores the BASE unit price (tier price + variant
     * delta) frozen at add time; the quantity discount is recomputed here on
     * every read, because it moves with `qty` and the customer edits that
     * freely in the cart. Everything downstream (cart view, checkout view,
     * order lines) reads `price` from here, so a single line of truth.
     *
     * Quantity is counted PER LINE — a product in two colours is two lines and
     * each needs to reach the threshold on its own.
     */
    public function lines(): array
    {
        $raw = $this->items();
        if (! $raw) {
            return [];
        }

        $products = Product::with('quantityTiers')
            ->whereIn('id', array_unique(array_column($raw, 'product_id')))
            ->get()
            ->keyBy('id');

        $clientType = auth('client')->user()?->type;

        foreach ($raw as $key => $line) {
            $qty     = (int) $line['qty'];
            $base    = (float) $line['price'];
            $product = $products->get($line['product_id']);
            $percent = $product ? $product->quantityDiscountPercent($qty, $clientType) : 0.0;

            $line['base_price']       = $base;
            $line['discount_percent'] = $percent;
            $line['price']            = $percent > 0 ? round($base * (1 - $percent / 100), 2) : $base;
            $line['line_total']       = $line['price'] * $qty;
            $line['next_tier']        = $product?->nextQuantityTier($qty, $clientType);

            $raw[$key] = $line;
        }

        return $raw;
    }

    /** Total saved thanks to quantity breaks, for the cart summary. */
    public function quantitySavings(): float
    {
        $saved = 0;
        foreach ($this->lines() as $line) {
            $saved += ($line['base_price'] - $line['price']) * $line['qty'];
        }

        return $saved;
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
                'name'        => $product->display_name,
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
        foreach ($this->lines() as $line) {
            $total += $line['line_total'];
        }

        return $total;
    }

    private function lineKey(int $productId, ?int $variantId): string
    {
        return $productId . ':' . ($variantId ?? 0);
    }
}
