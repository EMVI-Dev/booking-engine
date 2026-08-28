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
        Schema::create('reservations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('code', 32)->nullable()->unique();
            $table->string('bookable_type');
            $table->char('bookable_id', 26);
            $table->foreignUlid('operator_id')->constrained('operators')->cascadeOnDelete();
            $table->foreignUlid('guest_id')->nullable()->constrained('guests')->nullOnDelete();
            $table->string('guest_name');
            $table->string('guest_contact');
            $table->string('guest_email')->nullable();
            $table->date('requested_date');
            $table->unsignedInteger('pax_count')->default(1);
            $table->text('notes')->nullable();
            $table->json('terms_snapshot'); // Price, cancellation terms, inclusions frozen at booking time
            $table->string('status')->default('payment_pending'); // payment_pending, pending_confirmation, confirmed, declined, cancelled, completed, expired
            $table->dateTime('hold_expires_at')->nullable();
            $table->dateTime('review_request_sent_at')->nullable();
            $table->dateTime('departure_reminder_sent_at')->nullable();
            $table->timestamps();

            $table->index(['bookable_type', 'bookable_id']);
            $table->index(['operator_id', 'status']);
            $table->index(['status', 'hold_expires_at']);
            $table->index(['status', 'requested_date', 'review_request_sent_at'], 'reservations_review_request_idx');
            $table->index(['status', 'requested_date', 'departure_reminder_sent_at'], 'reservations_departure_reminder_idx');
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('reservation_id')->constrained('reservations')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('gateway')->default('doku');
            $table->string('gateway_ref')->nullable()->index();
            $table->json('split_details')->nullable();
            $table->string('status')->default('pending'); // pending, paid, failed, refunded, partially_refunded
            $table->string('refund_status')->nullable();
            $table->dateTime('refunded_at')->nullable();
            $table->timestamps();

            $table->index(['reservation_id', 'status']);
        });

        Schema::create('reviews', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('reservation_id')->unique()->constrained('reservations')->cascadeOnDelete();
            $table->string('bookable_type');
            $table->char('bookable_id', 26);
            $table->foreignUlid('operator_id')->constrained('operators')->cascadeOnDelete();
            $table->unsignedTinyInteger('rating'); // 1-5
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->index(['bookable_type', 'bookable_id']);
            $table->index('operator_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('reservations');
    }
};
