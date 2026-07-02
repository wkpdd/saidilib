<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name_fr');
            $table->string('name_ar')->nullable();
            $table->string('slug')->unique();
            $table->string('sku')->nullable();
            $table->string('brand')->nullable();
            $table->string('short_desc_fr', 500)->nullable();
            $table->string('short_desc_ar', 500)->nullable();
            $table->longText('description_fr')->nullable();
            $table->longText('description_ar')->nullable();
            $table->decimal('price', 12, 2)->default(0);            // DZD
            $table->decimal('compare_at_price', 12, 2)->nullable(); // old/struck price
            $table->unsignedInteger('stock')->default(0);
            $table->boolean('track_stock')->default(false);
            $table->string('main_image')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_new')->default(false);
            $table->boolean('free_shipping')->default(false);
            // Per-product marketing / SEO
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 500)->nullable();
            $table->unsignedInteger('views')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
