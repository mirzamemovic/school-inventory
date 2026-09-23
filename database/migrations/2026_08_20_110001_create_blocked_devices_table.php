<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The correct response to the most likely attack: a bored 16-year-old
     * filing joke reports is identified from the audit log by device token and
     * blocked here (spec section 13).
     */
    public function up(): void
    {
        Schema::create('blocked_devices', function (Blueprint $table) {
            $table->id();
            $table->string('device_token', 64)->unique();
            $table->string('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blocked_devices');
    }
};
