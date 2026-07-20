<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Access tokens for the native staff app. Purely additive — touches no
 * existing table or data (safe on the live site).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->string('device_name', 120)->nullable();
            $table->string('fcm_token', 255)->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_tokens');
    }
};
