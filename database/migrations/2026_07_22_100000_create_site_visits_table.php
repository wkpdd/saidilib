<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Visitor counter. One row per (day, anonymous visitor) with a view count —
 * visitors/day = COUNT(*), pages vues/day = SUM(views). Purely additive.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_visits', function (Blueprint $table) {
            $table->date('day');
            $table->string('visitor_hash', 40);
            $table->unsignedInteger('views')->default(1);
            $table->primary(['day', 'visitor_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_visits');
    }
};
