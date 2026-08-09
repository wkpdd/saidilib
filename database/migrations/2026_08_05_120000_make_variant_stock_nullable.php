<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Variant stock becomes nullable: NULL = "not counted for this option", which
 * is what the admin form has always promised ("vide = illimité").
 *
 * Until now an empty field was stored as 0, and 0 means "sold out" — so every
 * colour/size created without typing a stock disabled itself on the storefront
 * and the product could not be bought at all.
 *
 * The existing 0s are converted to NULL because they cannot be told apart from
 * "never filled", and leaving them would keep those products unsellable. After
 * this migration a 0 typed on purpose still means sold out.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->unsignedInteger('stock')->nullable()->default(null)->change();
        });

        DB::table('product_variants')->where('stock', 0)->update(['stock' => null]);
    }

    public function down(): void
    {
        DB::table('product_variants')->whereNull('stock')->update(['stock' => 0]);

        Schema::table('product_variants', function (Blueprint $table) {
            $table->unsignedInteger('stock')->nullable(false)->default(0)->change();
        });
    }
};
