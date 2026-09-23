<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // The asset tag prefix (spec section 6.2). Held on the category
            // rather than derived from its name, because a name can be edited
            // and a tag stuck to a wall can never change.
            $table->string('prefix', 8);
            $table->boolean('needs_inspection_default')->default(false);
            $table->unsignedSmallInteger('default_inspection_interval_months')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
