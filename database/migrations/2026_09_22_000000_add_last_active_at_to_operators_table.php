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
            $table->timestamp('last_active_at')->nullable()->after('updated_at')->index();
            $table->timestamp('inactivity_reminder_sent_at')->nullable()->after('last_active_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('operators', function (Blueprint $table) {
            $table->dropIndex(['last_active_at']);
            $table->dropColumn(['last_active_at', 'inactivity_reminder_sent_at']);
        });
    }
};
