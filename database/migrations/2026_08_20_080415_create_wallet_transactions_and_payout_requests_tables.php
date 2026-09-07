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
        Schema::create('payout_requests', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('reference_number')->unique();
            $table->foreignUlid('operator_id')->constrained('operators')->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('bank_provider');
            $table->string('bank_account_name');
            $table->string('bank_account_number');
            $table->string('status')->default('pending'); // pending, processing, completed, rejected
            $table->text('notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->string('proof_document_path')->nullable();
            $table->char('processed_by', 26)->nullable();
            $table->dateTime('processed_at')->nullable();
            $table->timestamps();

            $table->index(['operator_id', 'status']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('operator_id')->constrained('operators')->cascadeOnDelete();
            $table->foreignUlid('reservation_id')->nullable()->constrained('reservations')->nullOnDelete();
            $table->foreignUlid('payout_request_id')->nullable()->constrained('payout_requests')->nullOnDelete();
            $table->string('type'); // booking_earning, platform_commission, payout_withdrawal, refund_deduction, manual_adjustment
            $table->decimal('gross_amount', 14, 2)->default(0);
            $table->decimal('fee_amount', 14, 2)->default(0);
            $table->decimal('net_amount', 14, 2); // can be positive (credit) or negative (debit)
            $table->decimal('balance_snapshot', 14, 2)->nullable();
            $table->string('status')->default('pending_escrow'); // pending_escrow, cleared, cancelled
            $table->dateTime('available_at')->nullable();
            $table->string('description');
            $table->timestamps();

            $table->index(['operator_id', 'status']);
            $table->index(['status', 'available_at']);
            $table->index(['operator_id', 'type']);
            $table->index(['operator_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
        Schema::dropIfExists('payout_requests');
    }
};
