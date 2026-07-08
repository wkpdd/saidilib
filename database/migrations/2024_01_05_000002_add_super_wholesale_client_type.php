<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Extend the client type enum with a third pricing tier.
        DB::statement("ALTER TABLE clients MODIFY COLUMN type ENUM('retail','wholesale','super_wholesale') NOT NULL DEFAULT 'retail'");
    }

    public function down(): void
    {
        DB::statement("UPDATE clients SET type='wholesale' WHERE type='super_wholesale'");
        DB::statement("ALTER TABLE clients MODIFY COLUMN type ENUM('retail','wholesale') NOT NULL DEFAULT 'retail'");
    }
};
