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
        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('operator_id')->constrained('operators')->cascadeOnDelete();
            $table->foreignUlid('plan_id')->constrained('plans')->cascadeOnDelete();
            $table->foreignUlid('previous_plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $table->string('invoice_number')->unique();
            $table->string('type', 30)->default('subscription_upgrade'); // subscription_new, subscription_upgrade, subscription_renewal, subscription_downgrade
            $table->string('billing_interval', 20)->default('monthly'); // monthly, yearly
            $table->decimal('gross_amount', 14, 2)->default(0);
            $table->decimal('prorated_credit', 14, 2)->default(0);
            $table->decimal('net_amount_paid', 14, 2)->default(0);
            $table->string('status', 20)->default('completed'); // pending, completed, failed, refunded
            $table->string('gateway', 50)->default('manual'); // doku, manual, wallet_credit, simulation
            $table->string('gateway_ref')->nullable()->index();
            $table->json('breakdown')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['operator_id', 'created_at']);
            $table->index(['operator_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_payments');
    }
};
