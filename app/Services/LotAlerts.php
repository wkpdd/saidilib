<?php

namespace App\Services;

use App\Models\AdminNotification;
use App\Models\Setting;
use App\Models\StockReceiptItem;

/**
 * Expiry alerts for received lots. Shared hosting has no cron, so the scan
 * runs opportunistically when the admin dashboard loads — throttled to once
 * every 12h via a setting, deduped per lot via expiry_alerted_at.
 */
class LotAlerts
{
    public const WARN_DAYS = 30;

    public static function scanIfDue(): void
    {
        try {
            $last = Setting::get('lots_last_expiry_check');
            if ($last && now()->diffInHours($last) < 12 && now()->diffInHours($last) >= 0) {
                return;
            }
            Setting::put('lots_last_expiry_check', now()->toDateTimeString());

            $soon = StockReceiptItem::whereNotNull('expiry_date')
                ->whereNull('expiry_alerted_at')
                ->whereDate('expiry_date', '<=', now()->addDays(self::WARN_DAYS))
                ->with('product')
                ->whereHas('receipt', fn ($q) => $q->where('status', 'received'))
                ->limit(20)->get();

            foreach ($soon as $item) {
                $expired = $item->expiry_date->isPast();
                AdminNotification::raise(
                    'stock',
                    ($expired ? '⛔ Lot expiré — ' : '⏰ Lot expire bientôt — ') . ($item->product?->name_fr ?? $item->product_name),
                    'Lot ' . ($item->lot_number ?: '—') . ' · ' . $item->quantity . ' unité(s) · DLC ' . $item->expiry_date->format('d/m/Y'),
                    route('admin.products.edit', $item->product_id ?? 0),
                    '📦'
                );
                $item->forceFill(['expiry_alerted_at' => now()])->saveQuietly();
            }
        } catch (\Throwable $e) {
            // Alerting must never break the dashboard.
        }
    }

    /** Lots (received) for one product, newest first — traceability view. */
    public static function forProduct(int $productId)
    {
        return StockReceiptItem::where('product_id', $productId)
            ->whereHas('receipt', fn ($q) => $q->where('status', 'received'))
            ->with(['receipt.supplier', 'variant'])
            ->orderByDesc('id')->limit(30)->get();
    }
}
