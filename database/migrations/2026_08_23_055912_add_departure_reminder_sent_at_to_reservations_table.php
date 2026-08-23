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
        Schema::table('reservations', function (Blueprint $table) {
            $table->dateTime('departure_reminder_sent_at')->nullable()->after('review_request_sent_at');
            $table->index(['status', 'requested_date', 'departure_reminder_sent_at'], 'reservations_departure_reminder_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropIndex('reservations_departure_reminder_idx');
            $table->dropColumn('departure_reminder_sent_at');
        });
    }
};
