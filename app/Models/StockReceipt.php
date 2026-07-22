<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class StockReceipt extends Model
{
    protected $fillable = [
        'reference', 'supplier_id', 'stock_location_id', 'supplier_invoice', 'status', 'document_date',
        'received_at', 'total_cost', 'document_path', 'note', 'created_by',
    ];

    protected $casts = [
        'document_date' => 'date',
        'received_at'   => 'datetime',
        'total_cost'    => 'decimal:2',
    ];

    public const STATUS_LABELS = [
        'draft'     => 'Brouillon',
        'received'  => 'Reçu (stock mis à jour)',
        'cancelled' => 'Annulé',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockReceiptItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function generateReference(): string
    {
        do {
            $ref = 'BR-' . strtoupper(bin2hex(random_bytes(3)));
        } while (self::where('reference', $ref)->exists());

        return $ref;
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function getIsReceivedAttribute(): bool
    {
        return $this->status === 'received';
    }

    /** Recompute the document total from its line items. */
    public function recomputeTotal(): void
    {
        $this->update(['total_cost' => (float) $this->items()->sum('line_total')]);
    }

    /**
     * Mark the document as received and push every line's quantity into stock
     * (into the specific variant when set, otherwise the product). Idempotent:
     * a receipt can only be received once.
     */
    public function receiveInto(): bool
    {
        if ($this->status === 'received') {
            return false;
        }

        DB::transaction(function () {
            $stock = app(\App\Services\StockService::class);
            foreach ($this->items()->with('product', 'variant')->get() as $item) {
                if ($item->product_variant_id && $item->variant) {
                    $item->variant->increment('stock', $item->quantity);
                } elseif ($item->product_id && $item->product) {
                    $item->product->increment('stock', $item->quantity);
                }
                if ($item->product_id) {
                    $stock->receive(
                        $item->product_id, $item->product_variant_id, $item->quantity,
                        $this->stock_location_id, $this->reference, $this->created_by
                    );
                }
            }

            $this->update(['status' => 'received', 'received_at' => now()]);
        });

        return true;
    }
}
