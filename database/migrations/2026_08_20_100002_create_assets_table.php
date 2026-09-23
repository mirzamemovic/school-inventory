<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();

            // Identity
            $table->string('asset_tag')->unique();
            // The global sequence behind the tag, kept as an integer so the next
            // number never has to be parsed back out of a string.
            $table->unsignedInteger('tag_sequence')->unique();
            $table->string('official_number')->nullable();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name')->nullable();
            $table->string('make')->nullable();
            $table->string('model')->nullable();
            $table->string('serial_number')->nullable();
            $table->string('photo_path')->nullable();

            // Purchase
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_price', 10, 2)->nullable();
            $table->string('supplier')->nullable();
            $table->string('invoice_ref')->nullable();
            $table->string('funding_source')->default('unknown');
            $table->date('warranty_end')->nullable();

            // Location. Required for every real asset; null only while a
            // pre-printed blank label is still waiting to be stuck to something.
            $table->foreignId('room_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('assigned_person_id')->nullable();

            // State
            $table->string('status')->default('unassigned');
            $table->string('condition')->default('good');

            // Lifecycle
            $table->unsignedSmallInteger('expected_life_years')->nullable();
            $table->date('disposal_date')->nullable();
            $table->string('disposal_method')->nullable();

            // Compliance
            $table->boolean('needs_inspection')->default(false);
            $table->unsignedSmallInteger('inspection_interval_months')->nullable();
            // Denormalised cache of the newest row in inspections. Derived,
            // never edited by hand.
            $table->timestamp('last_inspected_at')->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index(['room_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
