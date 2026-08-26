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
        Schema::table('operators', function (Blueprint $table) {
            $table->string('subscription_interval', 20)->default('monthly')->after('plan_expires_at');
            $table->foreignUlid('pending_plan_id')->nullable()->after('subscription_interval')->constrained('plans')->nullOnDelete();
            $table->timestamp('pending_plan_action_at')->nullable()->after('pending_plan_id');
            $table->boolean('subscription_auto_renew')->default(true)->after('pending_plan_action_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('operators', function (Blueprint $table) {
            $table->dropForeign(['pending_plan_id']);
            $table->dropColumn([
                'subscription_interval',
                'pending_plan_id',
                'pending_plan_action_at',
                'subscription_auto_renew',
            ]);
        });
    }
};
