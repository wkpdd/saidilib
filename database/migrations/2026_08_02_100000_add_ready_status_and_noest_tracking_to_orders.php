<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "Prête à expédier" status + the Noest tracking snapshot we keep locally.
 *
 * Purely additive: 'ready' is APPENDED to the status enum (appending is an
 * instant, metadata-only change in MySQL 8 — inserting in the middle would
 * rebuild the table), and every new column is nullable. No existing row is
 * touched, so rolling back is safe.
 */
return new class extends Migration
{
    private const STATUSES = [
        'pending', 'confirmed', 'preparing', 'shipped',
        'delivered', 'cancelled', 'returned', 'ready',
    ];

    public function up(): void
    {
        $this->setStatusEnum(self::STATUSES);

        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('ready_at')->nullable()->after('dispatched_at');
            $table->string('noest_status', 150)->nullable()->after('ready_at');
            $table->string('noest_status_key', 60)->nullable()->after('noest_status');
            $table->string('noest_driver', 150)->nullable()->after('noest_status_key');
            $table->json('noest_activity')->nullable()->after('noest_driver');
            $table->timestamp('noest_checked_at')->nullable()->after('noest_activity');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'ready_at', 'noest_status', 'noest_status_key',
                'noest_driver', 'noest_activity', 'noest_checked_at',
            ]);
        });

        // Park any 'ready' order back on 'preparing' before shrinking the enum.
        DB::table('orders')->where('status', 'ready')->update(['status' => 'preparing']);

        $this->setStatusEnum(array_values(array_diff(self::STATUSES, ['ready'])));
    }

    private function setStatusEnum(array $values): void
    {
        if (DB::getDriverName() === 'mysql') {
            $list = implode(',', array_map(fn ($v) => "'{$v}'", $values));
            DB::statement("ALTER TABLE `orders` MODIFY `status` ENUM({$list}) NOT NULL DEFAULT 'pending'");

            return;
        }

        // sqlite (dev/tests) enforces the enum with a CHECK constraint; swapping
        // the column for a plain string drops it so the new value is accepted.
        Schema::table('orders', function (Blueprint $table) {
            $table->string('status', 20)->default('pending')->change();
        });
    }
};
