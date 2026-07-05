<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Goods-receipt / purchase document (bon de réception).
        Schema::create('stock_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();          // BR-XXXXX
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('supplier_invoice')->nullable();  // supplier's own invoice no.
            $table->enum('status', ['draft', 'received', 'cancelled'])->default('draft');
            $table->date('document_date')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->decimal('total_cost', 14, 2)->default(0);
            $table->string('document_path')->nullable();      // uploaded invoice/PDF/photo
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_receipts');
    }
};
