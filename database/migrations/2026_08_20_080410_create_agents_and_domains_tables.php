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
        Schema::create('agents', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('bio')->nullable();
            $table->string('photo')->nullable();
            $table->string('contact_whatsapp')->nullable();
            $table->string('booking_notification_email');
            $table->string('billing_email');
            $table->string('status')->default('pending'); // pending, approved, suspended
            $table->text('terms_and_conditions')->nullable();
            $table->string('bank_account_ref')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('favicon_path')->nullable();
            $table->string('banner_path')->nullable();
            $table->json('settings')->nullable(); // sellable_standalone_default, brand_color, display_name
            $table->timestamps();

            $table->index('status');
        });

        Schema::create('agent_users', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('agent_id')->constrained('agents')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role')->default('owner'); // owner, admin, reservation, finance
            $table->timestamps();

            $table->unique(['agent_id', 'user_id']);
        });

        Schema::create('agent_domains', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('agent_id')->constrained('agents')->cascadeOnDelete();
            $table->string('domain')->unique();
            $table->string('type'); // subdomain, custom
            $table->boolean('is_primary')->default(false);
            $table->string('status')->default('pending'); // pending, verifying, active, failed
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('ssl_issued_at')->nullable();
            $table->timestamps();

            $table->index(['agent_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_domains');
        Schema::dropIfExists('agent_users');
        Schema::dropIfExists('agents');
    }
};
