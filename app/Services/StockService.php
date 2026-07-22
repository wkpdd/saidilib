<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockLevel;
use App\Models\StockLocation;
use Illuminate\Support\Facades\DB;

/**
 * Per-location stock. Invariant kept simple on purpose:
 * products.stock (and variant stock) remain the authoritative TOTALS the
 * storefront uses; stock_levels is the per-location breakdown. Every write
 * here logs a stock_movements row for audit.
 */
class StockService
{
    /** Get-or-create the level row for one (location, product, variant). */
    private function level(int $locationId, int $productId, ?int $variantId): StockLevel
    {
        return StockLevel::firstOrCreate(
            ['stock_location_id' => $locationId, 'product_id' => $productId, 'product_variant_id' => $variantId],
            ['quantity' => 0]
        );
    }

    private function log(int $locationId, int $productId, ?int $variantId, int $delta, string $reason, ?string $note = null, ?int $userId = null): void
    {
        DB::table('stock_movements')->insert([
            'stock_location_id'  => $locationId,
            'product_id'         => $productId,
            'product_variant_id' => $variantId,
            'delta'              => $delta,
            'reason'             => $reason,
            'note'               => $note,
            'created_by'         => $userId,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);
    }

    /** Move quantity between two locations. Totals unchanged. */
    public function transfer(Product $product, ?ProductVariant $variant, int $fromId, int $toId, int $qty, ?int $userId = null): void
    {
        if ($qty <= 0 || $fromId === $toId) {
            return;
        }
        DB::transaction(function () use ($product, $variant, $fromId, $toId, $qty, $userId) {
            $from = $this->level($fromId, $product->id, $variant?->id);
            $to   = $this->level($toId, $product->id, $variant?->id);
            $from->decrement('quantity', $qty);
            $to->increment('quantity', $qty);
            $this->log($fromId, $product->id, $variant?->id, -$qty, 'transfer_out', null, $userId);
            $this->log($toId, $product->id, $variant?->id, $qty, 'transfer_in', null, $userId);
        });
    }

    /** Reception: add into the given (or default) location. Caller already incremented totals. */
    public function receive(int $productId, ?int $variantId, int $qty, ?int $locationId, ?string $note = null, ?int $userId = null): void
    {
        $locationId = $locationId ?: StockLocation::defaultLocation()?->id;
        if (! $locationId || $qty <= 0) {
            return;
        }
        $this->level($locationId, $productId, $variantId)->increment('quantity', $qty);
        $this->log($locationId, $productId, $variantId, $qty, 'receipt', $note, $userId);
    }

    /** Sale: remove from the default location (floor 0). Caller already decremented totals. */
    public function sale(int $productId, ?int $variantId, int $qty, ?string $note = null): void
    {
        $default = StockLocation::defaultLocation();
        if (! $default || $qty <= 0) {
            return;
        }
        $level = $this->level($default->id, $productId, $variantId);
        $level->update(['quantity' => max(0, $level->quantity - $qty)]);
        $this->log($default->id, $productId, $variantId, -$qty, 'sale', $note);
    }

    /** Manual set of one cell in the matrix (adjust reason, keeps totals in sync). */
    public function adjust(Product $product, ?ProductVariant $variant, int $locationId, int $newQty, ?int $userId = null): void
    {
        DB::transaction(function () use ($product, $variant, $locationId, $newQty, $userId) {
            $level = $this->level($locationId, $product->id, $variant?->id);
            $delta = $newQty - $level->quantity;
            if ($delta === 0) {
                return;
            }
            $level->update(['quantity' => $newQty]);
            // Keep the authoritative total aligned with the sum of locations.
            if ($variant) {
                $variant->increment('stock', $delta);
            } else {
                $product->increment('stock', $delta);
            }
            $this->log($locationId, $product->id, $variant?->id, $delta, 'adjust', null, $userId);
        });
    }
}
