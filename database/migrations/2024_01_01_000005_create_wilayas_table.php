<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('wilayas', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('code')->unique(); // 1..58
            $table->string('name_fr');
            $table->string('name_ar')->nullable();
            $table->decimal('home_fee', 10, 2)->default(0);    // livraison à domicile
            $table->decimal('stopdesk_fee', 10, 2)->default(0); // livraison au bureau
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wilayas');
    }
};
