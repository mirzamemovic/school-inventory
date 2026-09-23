<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The single audit log for the whole system. Append only: no updated_at,
     * because the moment rows can be edited the log stops being evidence.
     */
    public function up(): void
    {
        Schema::create('asset_events', function (Blueprint $table) {
            $table->id();
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->string('action');
            $table->string('actor_type');
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_name')->nullable();
            $table->string('device_token', 64)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['subject_type', 'subject_id']);
            $table->index('action');
            $table->index('created_at');
            $table->index('device_token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_events');
    }
};
