<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Storefront photo gallery; the count per operator is capped by plans.gallery_photo_limit.
     */
    public function up(): void
    {
        Schema::create('operator_gallery_photos', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('operator_id')->constrained('operators')->cascadeOnDelete();
            $table->string('path');
            $table->string('caption', 150)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['operator_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operator_gallery_photos');
    }
};
