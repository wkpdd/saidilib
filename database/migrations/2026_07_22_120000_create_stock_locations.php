<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-location stock. Purely additive: products.stock stays the
 * authoritative TOTAL used by the storefront; stock_levels is the
 * per-location breakdown, backfilled into the default location.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_locations', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->boolean('is_default')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('stock_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_location_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->integer('quantity')->default(0);
            $table->timestamps();
            $table->unique(['stock_location_id', 'product_id', 'product_variant_id'], 'stock_levels_unique');
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_location_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable();
            $table->integer('delta');
            $table->string('reason', 30); // transfer_in / transfer_out / receipt / sale / adjust / init
            $table->string('note', 190)->nullable();
            $table->foreignId('created_by')->nullable();
            $table->timestamps();
            $table->index(['product_id', 'created_at']);
        });

        Schema::table('stock_receipts', function (Blueprint $table) {
            $table->foreignId('stock_location_id')->nullable()->after('supplier_id');
        });
        Schema::table('stock_receipt_items', function (Blueprint $table) {
            $table->timestamp('expiry_alerted_at')->nullable()->after('expiry_date');
        });

        // Seed the two starting locations.
        $now = now();
        DB::table('stock_locations')->insert([
            ['name' => 'Magasin', 'is_default' => true, 'sort_order' => 0, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Dépôt', 'is_default' => false, 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
        ]);
        $default = DB::table('stock_locations')->where('is_default', true)->value('id');

        // Backfill: current totals land in the default location.
        DB::statement(
            'INSERT INTO stock_levels (stock_location_id, product_id, product_variant_id, quantity, created_at, updated_at)
             SELECT ?, id, NULL, COALESCE(stock, 0), NOW(), NOW() FROM products WHERE deleted_at IS NULL',
            [$default]
        );
        DB::statement(
            'INSERT INTO stock_levels (stock_location_id, product_id, product_variant_id, quantity, created_at, updated_at)
             SELECT ?, product_id, id, COALESCE(stock, 0), NOW(), NOW() FROM product_variants',
            [$default]
        );
    }

    public function down(): void
    {
        Schema::table('stock_receipt_items', fn (Blueprint $t) => $t->dropColumn('expiry_alerted_at'));
        Schema::table('stock_receipts', fn (Blueprint $t) => $t->dropColumn('stock_location_id'));
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stock_levels');
        Schema::dropIfExists('stock_locations');
    }
};
