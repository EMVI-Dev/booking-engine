<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Guest questions and private / group trip requests from the storefront contact form.
     */
    public function up(): void
    {
        Schema::create('enquiries', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('operator_id')->constrained('operators')->cascadeOnDelete();
            $table->string('type', 20); // general | private_group
            $table->string('name');
            $table->string('whatsapp', 30);
            $table->string('email')->nullable();
            $table->date('preferred_date')->nullable();
            $table->unsignedSmallInteger('group_size')->nullable();
            $table->text('message');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['operator_id', 'created_at']);
            $table->index(['operator_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enquiries');
    }
};
