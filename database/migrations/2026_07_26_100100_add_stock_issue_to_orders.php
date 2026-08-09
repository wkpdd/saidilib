<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An order for more units than we hold is accepted (we'd rather call the
 * customer than lose the sale) but flagged here, so the admin sees exactly
 * which line came up short and can settle it with the client.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('has_stock_issue')->default(false)->after('status');
            $table->text('stock_issue')->nullable()->after('has_stock_issue');
            $table->timestamp('stock_issue_resolved_at')->nullable()->after('stock_issue');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['has_stock_issue', 'stock_issue', 'stock_issue_resolved_at']);
        });
    }
};
