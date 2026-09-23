<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('unit')->default('pieces');
            $table->integer('quantity')->default(0);
            $table->unsignedInteger('minimum_level')->default(0);
            // Where the cupboard is. The public cupboard page is addressed by
            // room, so a supply has to know which room it lives in; matching a
            // free-text location to a room name would break the first time
            // somebody renames a room.
            $table->foreignId('room_id')->nullable()->constrained()->nullOnDelete();
            // And whereabouts inside it: "second shelf, left".
            $table->string('storage_location')->nullable();
            $table->timestamp('last_counted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplies');
    }
};
