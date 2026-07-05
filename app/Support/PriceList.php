<?php

namespace App\Support;

use App\Models\Product;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Builds the downloadable B2B price-list PDF from the active catalogue,
 * grouped by category. French-language business document (dompdf renders
 * Latin cleanly; Arabic product names would need a reshaped font, so we use
 * the French designation here).
 */
class PriceList
{
    public static function pdf(?string $clientName = null)
    {
        $groups = Product::where('is_active', true)
            ->with('category')
            ->orderBy('category_id')
            ->orderBy('name_fr')
            ->get()
            ->groupBy(fn ($p) => $p->category?->name_fr ?? 'Divers');

        return Pdf::loadView('pdf.pricelist', [
            'groups'     => $groups,
            'store'      => Setting::get('store_name', 'Saidi Papetrie'),
            'phone'      => Setting::get('phone'),
            'email'      => Setting::get('email'),
            'currency'   => Setting::get('currency', 'DA'),
            'clientName' => $clientName,
            'date'       => now(),
        ])->setPaper('a4');
    }

    public static function filename(): string
    {
        return 'tarifs-saidi-' . now()->format('Y-m-d') . '.pdf';
    }
}
