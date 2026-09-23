<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tracked per title, not per copy. There is no separate "grade book set"
     * table either: the Grade 10 set is every title where grade = 10
     * (spec section 5).
     */
    public function up(): void
    {
        Schema::create('book_titles', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('subject')->nullable();
            $table->unsignedSmallInteger('grade');
            $table->string('isbn')->nullable();
            $table->unsignedInteger('total_copies')->default(0);
            $table->timestamps();

            $table->index('grade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('book_titles');
    }
};
