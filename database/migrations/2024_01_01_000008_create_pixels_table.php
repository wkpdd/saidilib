<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pixels', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('provider', ['facebook', 'tiktok', 'google', 'snapchat'])->default('facebook');
            $table->string('pixel_id');
            $table->string('access_token')->nullable(); // for server-side / CAPI (optional)
            $table->boolean('is_active')->default(true);
            $table->boolean('is_global')->default(true); // fires on every page when true
            $table->timestamps();
        });

        // Per-product pixels (product pages support pixels)
        Schema::create('pixel_product', function (Blueprint $table) {
            $table->foreignId('pixel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->primary(['pixel_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pixel_product');
        Schema::dropIfExists('pixels');
    }
};
