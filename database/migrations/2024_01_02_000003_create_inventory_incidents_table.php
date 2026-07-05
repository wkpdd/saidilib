<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('inventory_incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name');                     // snapshot in case product is deleted
            $table->enum('type', ['lost', 'broken', 'expired', 'theft', 'other'])->default('broken');
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('cost_estimate', 12, 2)->default(0); // financial loss estimate
            $table->text('reason')->nullable();
            $table->boolean('stock_adjusted')->default(false);   // did we decrement product stock?
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_incidents');
    }
};
