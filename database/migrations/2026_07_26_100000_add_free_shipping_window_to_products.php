<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Limited-time free delivery per product. The `free_shipping` flag already
 * existed (unused); this adds the optional end date that makes it a campaign.
 * Blank date = runs until the admin switches it off.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->date('free_shipping_until')->nullable()->after('free_shipping');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('free_shipping_until');
        });
    }
};
