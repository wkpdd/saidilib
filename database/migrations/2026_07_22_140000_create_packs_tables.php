<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** School supply packs (e.g. "Pack 1ère année moyenne"). Purely additive. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('packs', function (Blueprint $table) {
            $table->id();
            $table->string('name_fr', 190);
            $table->string('name_ar', 190)->nullable();
            $table->string('slug', 190)->unique();
            $table->text('description_fr')->nullable();
            $table->text('description_ar')->nullable();
            $table->string('image')->nullable();
            // NULL → the pack costs the sum of its items; set → promo price.
            $table->decimal('price', 12, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('pack_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pack_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pack_items');
        Schema::dropIfExists('packs');
    }
};
