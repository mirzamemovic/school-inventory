<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            // Short code so someone can check on their own report later
            // without an account (spec section 3.1, /r/{reference}).
            $table->string('reference', 12)->unique();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();

            $table->string('reporter_name');
            $table->string('reporter_type');
            $table->string('device_token', 64)->nullable();
            $table->string('ip_address', 45)->nullable();

            $table->text('description');
            $table->string('photo_path')->nullable();

            $table->string('status')->default('open');

            // Snapshotted when the report is created so the asset can be
            // returned to where it actually was, not to a hardcoded guess
            // (spec section 7B).
            $table->string('asset_status_before');
            $table->string('asset_condition_before');

            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();

            // Viewed means the director knows. Acknowledged means somebody is
            // acting. The gap between them is where things rot.
            $table->timestamp('first_viewed_at')->nullable();
            $table->foreignId('first_viewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution_note')->nullable();

            $table->index(['status', 'created_at']);
            $table->index('device_token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
