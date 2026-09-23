<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The pending label sheet, kept per user in the database rather than in the
     * session, so labels queued on a phone in a storeroom are still waiting at
     * the desktop attached to the printer.
     */
    public function up(): void
    {
        Schema::create('label_queue_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'asset_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('label_queue_items');
    }
};
