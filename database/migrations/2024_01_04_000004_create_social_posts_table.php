<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Log of products published to social platforms (real API posts).
        Schema::create('social_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('platform', 20);                  // facebook | instagram | telegram
            $table->enum('status', ['success', 'failed'])->default('success');
            $table->string('external_id')->nullable();       // post id
            $table->string('permalink')->nullable();
            $table->text('message')->nullable();             // error or info
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_posts');
    }
};
