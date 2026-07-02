<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name_fr');
            $table->string('name_ar')->nullable();
            $table->string('slug')->unique();
            $table->text('description_fr')->nullable();
            $table->text('description_ar')->nullable();
            $table->string('image')->nullable();
            $table->string('icon')->nullable();          // emoji or svg key for the "industry" feel
            $table->string('color', 20)->default('#2563eb');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
