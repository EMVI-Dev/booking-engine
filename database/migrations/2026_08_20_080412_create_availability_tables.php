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
        Schema::create('product_availability', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('product_id')->constrained('products')->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('capacity_booked')->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'date']);
        });

        Schema::create('availability_blocks', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('operator_id')->constrained('operators')->cascadeOnDelete();
            $table->foreignUlid('product_id')->nullable()->constrained('products')->cascadeOnDelete();
            $table->date('date_start');
            $table->date('date_end');
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->index(['operator_id', 'date_start', 'date_end']);
            $table->index('product_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('availability_blocks');
        Schema::dropIfExists('product_availability');
    }
};
