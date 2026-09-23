<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Unlabelled furniture, counted per room. Nobody cares about this
     * particular chair, only how many there are (spec section 4).
     */
    public function up(): void
    {
        Schema::create('room_contents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->string('item_name');
            $table->unsignedInteger('quantity')->default(1);
            $table->string('condition')->default('good');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_contents');
    }
};
