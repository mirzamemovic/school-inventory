<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Staff and students in one table. At 120 rows, splitting them buys
     * nothing (spec section 5).
     */
    public function up(): void
    {
        Schema::create('people', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type');
            $table->string('class')->nullable();
            $table->string('email')->nullable();
            // Deactivate, never delete: old book loans point at these records.
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['type', 'class']);
            $table->index('active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('people');
    }
};
