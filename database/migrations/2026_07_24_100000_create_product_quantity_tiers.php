<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-product quantity breaks ("achetez 10+, -5%"). Purely additive: a product
 * with no rows here behaves exactly as before. Retail viewers only — wholesale
 * clients keep their negotiated tier price with no stacking (see Product).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_quantity_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('min_qty');
            $table->decimal('discount_percent', 5, 2);
            $table->timestamps();

            $table->unique(['product_id', 'min_qty'], 'pqt_product_minqty_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_quantity_tiers');
    }
};
