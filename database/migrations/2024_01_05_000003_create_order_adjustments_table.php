<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Audit log of admin price edits on an order (before confirming a deal).
        Schema::create('order_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('label');                       // what changed (product name / "Total")
            $table->decimal('old_price', 12, 2)->nullable();
            $table->decimal('new_price', 12, 2)->nullable();
            $table->decimal('old_total', 14, 2)->nullable();
            $table->decimal('new_total', 14, 2)->nullable();
            $table->string('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_adjustments');
    }
};
