<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('client_id')->nullable()->after('reference')
                ->constrained()->nullOnDelete();

            // Refund tracking
            $table->decimal('refund_amount', 12, 2)->nullable()->after('total');
            $table->string('refund_method')->nullable()->after('refund_amount'); // cash | store_credit | delivery
            $table->string('refund_reason')->nullable()->after('refund_method');
            $table->timestamp('refunded_at')->nullable()->after('refund_reason');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('client_id');
            $table->dropColumn(['refund_amount', 'refund_method', 'refund_reason', 'refunded_at']);
        });
    }
};
