<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            // A variant now represents a specific (colour, size) combination with
            // its own stock. Either axis may be null for single-axis products.
            $table->string('color')->nullable()->after('label_ar');
            $table->string('color_hex', 9)->nullable()->after('color');
            $table->string('size')->nullable()->after('color_hex');
        });
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn(['color', 'color_hex', 'size']);
        });
    }
};
