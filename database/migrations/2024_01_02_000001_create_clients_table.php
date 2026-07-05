<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable()->unique();
            $table->string('phone', 30)->nullable();
            $table->string('password')->nullable();            // null => B2B client without storefront login
            $table->enum('type', ['retail', 'wholesale'])->default('retail');
            $table->foreignId('wilaya_id')->nullable()->constrained()->nullOnDelete();
            $table->string('commune')->nullable();
            $table->text('address')->nullable();
            $table->decimal('credit_limit', 12, 2)->default(0); // 0 = no credit allowed
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
