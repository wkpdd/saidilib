<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // "Sizes" / selectable options for a product. A variant can optionally
        // map to one of the product images, so picking a size can swap the photo.
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('image_id')->nullable()->constrained('product_images')->nullOnDelete();
            $table->string('label_fr');                 // e.g. "A4", "Grand format", "Rouge"
            $table->string('label_ar')->nullable();
            $table->string('option_group')->default('size'); // size | color | format ...
            $table->decimal('price_delta', 12, 2)->default(0);
            $table->unsignedInteger('stock')->default(0);
            $table->boolean('track_stock')->default(false);
            $table->string('sku')->nullable();
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
