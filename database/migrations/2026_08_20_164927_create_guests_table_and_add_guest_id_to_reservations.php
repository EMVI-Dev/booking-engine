<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('guests', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('agent_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('email')->nullable()->index();
            $table->string('phone')->nullable()->index();
            $table->text('notes')->nullable();
            $table->json('tags')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['agent_id', 'email']);
            $table->index(['agent_id', 'phone']);
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->foreignUlid('guest_id')->nullable()->after('agent_id')->constrained('guests')->nullOnDelete();
        });

        // Backfill existing reservations into the guests table
        $reservations = DB::table('reservations')->get();
        foreach ($reservations as $r) {
            $email = trim((string) $r->guest_email);
            $normalizedEmail = $email !== '' ? strtolower($email) : null;
            $phone = trim((string) $r->guest_contact);

            // Find existing guest for this agent
            $guestQuery = DB::table('guests')->where('agent_id', $r->agent_id);
            if ($normalizedEmail !== null) {
                $guestQuery->where('email', $normalizedEmail);
            } elseif ($phone !== '') {
                $guestQuery->where('phone', $phone);
            }

            $existingGuest = $guestQuery->first();

            if ($existingGuest) {
                $guestId = $existingGuest->id;
            } else {
                $guestId = (string) Str::ulid();
                DB::table('guests')->insert([
                    'id' => $guestId,
                    'agent_id' => $r->agent_id,
                    'name' => $r->guest_name,
                    'email' => $normalizedEmail,
                    'phone' => $phone !== '' ? $phone : null,
                    'created_at' => $r->created_at ?? now(),
                    'updated_at' => $r->updated_at ?? now(),
                ]);
            }

            DB::table('reservations')->where('id', $r->id)->update([
                'guest_id' => $guestId,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('guest_id');
        });

        Schema::dropIfExists('guests');
    }
};
