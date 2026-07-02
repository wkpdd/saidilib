<?php

namespace App\Services;

use App\Models\Pixel;
use App\Models\Product;
use Illuminate\Support\Facades\Schema;

/**
 * Collects which tracking pixels should fire on the current page:
 *  - global pixels fire everywhere
 *  - product-scoped pixels fire on that product's page
 */
class PixelService
{
    public function forPage(?Product $product = null): \Illuminate\Support\Collection
    {
        if (! Schema::hasTable('pixels')) {
            return collect();
        }

        $global = Pixel::active()->where('is_global', true)->get();

        if ($product) {
            $scoped = $product->pixels()->where('is_active', true)->get();
            return $global->concat($scoped)->unique('id')->values();
        }

        return $global->values();
    }
}
