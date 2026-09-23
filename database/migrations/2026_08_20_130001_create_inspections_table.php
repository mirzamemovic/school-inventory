<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A domain table, not a second audit log. An inspection is a business fact
     * with an inspector, a result and legal weight for fire safety — storing it
     * only as an asset_events row would bury the result in a JSON column where
     * nothing can react to it (spec section 5).
     */
    public function up(): void
    {
        Schema::create('inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->timestamp('inspected_at');
            $table->string('inspector_name');
            $table->string('result');
            $table->text('notes')->nullable();
            $table->timestamp('next_due_at')->nullable();
            $table->string('device_token', 64)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['asset_id', 'inspected_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspections');
    }
};
