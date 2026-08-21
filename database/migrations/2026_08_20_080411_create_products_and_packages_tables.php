<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('operator_id')->constrained('operators')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('cover_photo')->nullable();
            $table->json('gallery')->nullable();
            $table->string('location')->nullable();
            $table->string('category')->nullable();
            $table->unsignedInteger('capacity_per_day')->default(1);
            $table->decimal('price', 12, 2)->nullable();
            $table->boolean('sellable_standalone')->default(false);
            $table->json('inclusions')->nullable();
            $table->json('exclusions')->nullable();
            $table->text('terms_and_conditions')->nullable();
            $table->decimal('avg_rating', 3, 2)->default(0.00);
            $table->unsignedInteger('free_cancellation_hours')->default(24);
            $table->unsignedInteger('advance_booking_hours')->default(0);
            $table->text('cancellation_terms')->nullable();
            $table->string('status')->default('draft'); // draft, published
            $table->timestamps();

            $table->unique(['operator_id', 'slug']);
            $table->index(['operator_id', 'status']);
        });

        Schema::create('packages', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('operator_id')->constrained('operators')->cascadeOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->text('itinerary_text')->nullable();
            $table->string('cover_photo')->nullable();
            $table->json('gallery')->nullable();
            $table->string('location')->nullable();
            $table->string('category')->nullable();
            $table->decimal('price', 12, 2);
            $table->json('inclusions')->nullable();
            $table->json('exclusions')->nullable();
            $table->text('terms_and_conditions')->nullable();
            $table->decimal('avg_rating', 3, 2)->default(0.00);
            $table->unsignedInteger('free_cancellation_hours')->default(24);
            $table->unsignedInteger('advance_booking_hours')->default(0);
            $table->text('cancellation_terms')->nullable();
            $table->string('status')->default('draft'); // draft, published
            $table->timestamps();

            $table->unique(['operator_id', 'slug']);
            $table->index(['operator_id', 'status']);
        });

        Schema::create('package_products', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('package_id')->constrained('packages')->cascadeOnDelete();
            $table->foreignUlid('product_id')->constrained('products')->cascadeOnDelete();
            $table->unsignedInteger('quantity_required')->default(1);
            $table->timestamps();

            $table->unique(['package_id', 'product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('package_products');
        Schema::dropIfExists('packages');
        Schema::dropIfExists('products');
    }
};
