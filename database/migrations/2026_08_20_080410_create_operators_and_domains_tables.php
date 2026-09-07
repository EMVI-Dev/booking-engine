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
        Schema::create('operators', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('bio')->nullable();
            $table->string('photo')->nullable();
            $table->string('contact_whatsapp')->nullable();
            $table->string('booking_notification_email');
            $table->string('billing_email');
            $table->string('status')->default('pending'); // pending, approved, suspended
            $table->boolean('is_demo')->default(false);
            $table->foreignUlid('plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $table->timestamp('subscribed_at')->nullable();
            $table->timestamp('plan_expires_at')->nullable()->index();
            $table->string('subscription_interval', 20)->default('monthly');
            $table->foreignUlid('pending_plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $table->timestamp('pending_plan_action_at')->nullable()->index();
            $table->boolean('subscription_auto_renew')->default(true);
            $table->text('terms_and_conditions')->nullable();
            $table->string('bank_provider')->nullable();
            $table->string('bank_account_name')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->string('bank_account_ref')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('favicon_path')->nullable();
            $table->string('banner_path')->nullable();
            $table->json('settings')->nullable(); // sellable_standalone_default, brand_color, display_name, payment_gateway, whatsapp_schedule, social_links, tracking, marketing
            $table->timestamps();

            $table->index('status');
            $table->index('plan_id');
            $table->index('is_demo');
        });

        Schema::create('operator_users', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('operator_id')->constrained('operators')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role')->default('owner'); // owner, manager, staff
            $table->timestamps();

            $table->unique(['operator_id', 'user_id']);
        });

        Schema::create('operator_domains', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('operator_id')->constrained('operators')->cascadeOnDelete();
            $table->string('domain')->unique();
            $table->string('type'); // subdomain, custom
            $table->boolean('is_primary')->default(false);
            $table->string('status')->default('pending'); // pending, verifying, active, failed
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('ssl_issued_at')->nullable();
            $table->timestamps();

            $table->index(['operator_id', 'status']);
            $table->index(['type', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('operator_domains');
        Schema::dropIfExists('operator_users');
        Schema::dropIfExists('operators');
    }
};
