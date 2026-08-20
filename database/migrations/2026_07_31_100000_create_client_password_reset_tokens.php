<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Storefront customers reset their own password through a separate broker, so
 * their tokens live in their own table — a client and a staff user may share
 * an email address without one reset invalidating the other.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('client_password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_password_reset_tokens');
    }
};
