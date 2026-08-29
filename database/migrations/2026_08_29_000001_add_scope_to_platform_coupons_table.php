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
        Schema::table('platform_coupons', function (Blueprint $table) {
            if (! Schema::hasColumn('platform_coupons', 'scope')) {
                $table->string('scope', 20)->default('guest')->after('description');
                $table->index(['scope', 'is_active']);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('platform_coupons', function (Blueprint $table) {
            if (Schema::hasColumn('platform_coupons', 'scope')) {
                $table->dropIndex(['scope', 'is_active']);
                $table->dropColumn('scope');
            }
        });
    }
};
