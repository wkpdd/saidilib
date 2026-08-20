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

        // Variants referenced by loose lines, so their price_delta can be
        // re-applied when we re-price against the current tier.
        $variantIds = array_filter(array_column($raw, 'variant_id'));
        $variants = $variantIds
            ? ProductVariant::whereIn('id', $variantIds)->get()->keyBy('id')
            : collect();

        $clientType = auth('client')->user()?->type;

        foreach ($raw as $key => $line) {
            $qty     = (int) $line['qty'];
            $product = $products->get($line['product_id']);
            $isPack  = ! empty($line['pack_id']);

            // Re-price loose lines from the CURRENT client tier so the base and
            // the tier can never diverge (e.g. added as guest/retail, then logs
            // in as wholesale — previously kept the retail base). Pack lines keep
            // their frozen promo price; a vanished product keeps its stored price.
            if ($isPack || ! $product) {
                $base = (float) $line['price'];
            } else {
                $delta = isset($line['variant_id'])
                    ? (float) ($variants->get($line['variant_id'])?->price_delta ?? 0)
                    : 0.0;
                $base = max(0, $product->priceForTier($clientType) + $delta);
            }

            // Pack lines already carry the pack's own discount: stacking the
            // quantity breaks on top would discount the same unit twice.
            $percent = ($product && ! $isPack) ? $product->quantityDiscountPercent($qty, $clientType) : 0.0;

            $line['base_price']       = $base;
            $line['discount_percent'] = $percent;
            $line['price']            = $percent > 0 ? round($base * (1 - $percent / 100), 2) : $base;
            $line['line_total']       = $line['price'] * $qty;
            $line['next_tier']        = $isPack ? null : $product?->nextQuantityTier($qty, $clientType);

            $raw[$key] = $line;
        }

        return $raw;
    }

    /**
     * Cart lines arranged for display: loose items first, then one group per
     * pack. A pack is sold as a whole, so the view edits the group — never a
     * single line inside it.
     *
     * @return array<int, array{pack: ?array, lines: array}>
     */
    public function groups(): array
    {
        $loose = [];
        $packs = [];

        foreach ($this->lines() as $key => $line) {
            $packId = (int) ($line['pack_id'] ?? 0);

            if (! $packId) {
                $loose[$key] = $line;
                continue;
            }

            $packs[$packId] ??= [
                'pack'  => [
                    'id'    => $packId,
                    'name'  => $line['pack_name'] ?? 'Pack',
                    'slug'  => $line['pack_slug'] ?? null,
                    'qty'   => $this->packQty($line),
                    'total' => 0.0,
                ],
                'lines' => [],
            ];

            $packs[$packId]['lines'][$key] = $line;
            $packs[$packId]['pack']['total'] += $line['line_total'];
        }

        return array_values(array_merge(
            $loose ? [['pack' => null, 'lines' => $loose]] : [],
            array_values($packs)
        ));
    }

    /** How many copies of the pack a line represents. */
    private function packQty(array $line): int
    {
        $unit = max(1, (int) ($line['pack_unit_qty'] ?? 1));

        return max(1, (int) floor((int) $line['qty'] / $unit));
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

    /**
     * True when the whole cart ships free — EVERY product in it must have a
     * running free-delivery campaign. One ordinary item and the fee comes back,
     * so the promo can't be farmed by tacking a cheap product onto the order.
     */
    public function hasFreeShipping(): bool
    {
        $ids = array_unique(array_column($this->items(), 'product_id'));
        if (! $ids) {
            return false;
        }

        $products = Product::whereIn('id', $ids)->get(['id', 'free_shipping', 'free_shipping_until']);

        // A missing product (deleted since it was added) counts as not free.
        return $products->count() === count($ids)
            && $products->every(fn (Product $p) => $p->has_free_shipping);
    }

    /**
     * @param  array{id: int, name: string, slug: ?string}|null  $pack
     *         Set when the line comes from a pack: it then lives on its own
     *         key (never merged with the same product bought loose) and its
     *         discounted unit price can only be sold in whole packs.
     */
    public function add(Product $product, ?ProductVariant $variant, int $qty = 1, ?float $unitPrice = null, ?array $pack = null): void
    {
        $qty = max(1, $qty);
        $key = $this->lineKey($product->id, $variant?->id, $pack['id'] ?? null);
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
                'pack_id'       => $pack['id'] ?? null,
                'pack_name'     => $pack['name'] ?? null,
                'pack_slug'     => $pack['slug'] ?? null,
                'pack_unit_qty' => $pack ? $qty : null,      // units per single pack
            ];
        }

        Session::put(self::KEY, $cart);
    }

    /**
     * Change a loose line. Returns false — and changes nothing — for a line
     * that belongs to a pack: those carry the pack's discounted unit price,
     * so editing one alone would sell pack pricing on a single item.
     */
    public function update(string $key, int $qty): bool
    {
        $cart = $this->items();
        if (! isset($cart[$key])) {
            return false;
        }
        if (! empty($cart[$key]['pack_id'])) {
            return false;
        }

        if ($qty <= 0) {
            unset($cart[$key]);
        } else {
            $cart[$key]['qty'] = $qty;
        }
        Session::put(self::KEY, $cart);

        return true;
    }

    /** Buy N copies of a pack — every line scales together. 0 removes it. */
    public function updatePack(int $packId, int $packs): void
    {
        $packs = max(0, $packs);
        $cart = $this->items();

        foreach ($cart as $key => $line) {
            if ((int) ($line['pack_id'] ?? 0) !== $packId) {
                continue;
            }
            if ($packs === 0) {
                unset($cart[$key]);
                continue;
            }
            $cart[$key]['qty'] = max(1, (int) ($line['pack_unit_qty'] ?? 1)) * $packs;
        }

        Session::put(self::KEY, $cart);
    }

    public function removePack(int $packId): void
    {
        $this->updatePack($packId, 0);
    }

    /** Removing any line of a pack removes the whole pack — it is one product. */
    public function remove(string $key): void
    {
        $cart = $this->items();

        if (! empty($cart[$key]['pack_id'])) {
            $this->removePack((int) $cart[$key]['pack_id']);

            return;
        }

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

    private function lineKey(int $productId, ?int $variantId, ?int $packId = null): string
    {
        return ($packId ? 'pack' . $packId . ':' : '') . $productId . ':' . ($variantId ?? 0);
    }
}
