<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->string('code', 32)->nullable()->unique()->after('id');
        });

        // Backfill existing reservations
        $reservations = DB::table('reservations')->whereNull('code')->get();
        foreach ($reservations as $r) {
            $code = 'RSV-'.strtoupper(substr((string) $r->id, -8));
            DB::table('reservations')->where('id', $r->id)->update(['code' => $code]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn('code');
        });
    }
};
