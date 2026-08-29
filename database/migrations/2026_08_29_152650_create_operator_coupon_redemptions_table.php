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
        Schema::create('operator_coupon_redemptions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('platform_coupon_id')->constrained('platform_coupons')->cascadeOnDelete();
            $table->foreignUlid('operator_id')->constrained('operators')->cascadeOnDelete();
            // billing_cycle: e.g. "2026-08" for monthly, "2026" for annual — used for once_per_period check
            $table->string('billing_cycle', 10)->nullable();
            $table->timestamp('redeemed_at');
            $table->timestamps();

            $table->index(['platform_coupon_id', 'operator_id']);
            $table->index(['operator_id', 'billing_cycle']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('operator_coupon_redemptions');
    }
};
