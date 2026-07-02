<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();      // SAIDI-XXXXX
            $table->string('customer_name');
            $table->string('phone', 30);
            $table->string('phone2', 30)->nullable();
            $table->foreignId('wilaya_id')->nullable()->constrained()->nullOnDelete();
            $table->string('commune')->nullable();
            $table->text('address')->nullable();
            $table->enum('delivery_type', ['home', 'stopdesk'])->default('home');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('delivery_fee', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->string('payment_method')->default('cod');
            $table->enum('status', [
                'pending', 'confirmed', 'preparing', 'shipped',
                'delivered', 'cancelled', 'returned',
            ])->default('pending');
            $table->text('notes')->nullable();
            // Delivery provider dispatch
            $table->string('delivery_provider')->nullable(); // noest | yalidine | manual
            $table->string('tracking_number')->nullable();
            $table->json('provider_payload')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            // Marketing attribution (for pixels / CAPI)
            $table->string('utm_source')->nullable();
            $table->string('fbp')->nullable();
            $table->string('fbc')->nullable();
            $table->string('ip', 64)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
