<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Promo codes were unique across the whole platform, so an operator choosing a code another
     * operator (or the platform) already used hit a database error. Codes are now unique per owner:
     * per scope and operator. Platform-wide subscription codes stay unique through validation.
     */
    public function up(): void
    {
        Schema::table('platform_coupons', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->unique(['scope', 'operator_id', 'code'], 'platform_coupons_owner_code_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('platform_coupons', function (Blueprint $table) {
            $table->dropUnique('platform_coupons_owner_code_unique');
            $table->unique('code');
        });
    }
};
