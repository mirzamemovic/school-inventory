<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('book_loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained()->cascadeOnDelete();
            $table->foreignId('book_title_id')->constrained()->cascadeOnDelete();
            $table->date('issued_at');
            $table->date('due_at')->nullable();
            $table->date('returned_at')->nullable();
            $table->string('condition_on_return')->nullable();
            $table->text('notes')->nullable();
            $table->string('academic_year', 9);
            $table->timestamps();

            $table->index(['person_id', 'returned_at']);
            $table->index(['academic_year', 'returned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('book_loans');
    }
};
