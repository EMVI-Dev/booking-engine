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
            // How many times an operator can redeem this coupon:
            //   unlimited          – every checkout/renewal as long as the coupon is active
            //   first_purchase_only – only on the operator's very first subscription checkout
            //   once_per_period    – one redemption per billing cycle (month/year)
            $table->string('redemption_scope', 30)->default('unlimited')->after('operator_id');

            // JSON eligibility rule for auto-broadcast (nullable = manual only)
            // Example: {"type":"min_monthly_transactions","threshold":50,"lookback_months":1}
            $table->json('eligibility_rule')->nullable()->after('redemption_scope');

            // Optional linked platform announcement (shown in operator dashboard)
            $table->foreignUlid('announcement_id')
                ->nullable()
                ->constrained('platform_announcements')
                ->nullOnDelete()
                ->after('eligibility_rule');

            $table->index('redemption_scope');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('platform_coupons', function (Blueprint $table) {
            $table->dropConstrainedForeignId('announcement_id');
            $table->dropIndex(['redemption_scope']);
            $table->dropColumn(['redemption_scope', 'eligibility_rule', 'announcement_id']);
        });
    }
};
