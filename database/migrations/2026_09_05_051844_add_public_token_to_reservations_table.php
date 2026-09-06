<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table): void {
            $table->string('public_token', 64)->nullable()->after('code');
            $table->unique('public_token');
        });

        DB::table('reservations')->select('id')->orderBy('id')->chunk(200, function ($reservations): void {
            foreach ($reservations as $reservation) {
                DB::table('reservations')
                    ->where('id', $reservation->id)
                    ->update(['public_token' => Str::random(48)]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table): void {
            $table->dropUnique(['public_token']);
            $table->dropColumn('public_token');
        });
    }
};
