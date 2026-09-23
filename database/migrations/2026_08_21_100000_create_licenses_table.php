<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A licence is not an asset. It has no room, no condition, no QR code and
     * cannot be reported broken, so it gets its own table rather than being
     * bent into `assets` (spec section 5).
     */
    public function up(): void
    {
        Schema::create('licenses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('vendor')->nullable();
            $table->string('type')->default('software');
            $table->string('licence_model')->default('subscription');

            // Plain numbers, deliberately. At twenty staff a seat-assignment
            // table would be maintained for two weeks and then be permanently
            // wrong. Nullable because a domain name has no seats at all.
            $table->unsignedInteger('seats_total')->nullable();
            $table->unsignedInteger('seats_used')->nullable();

            $table->date('purchase_date')->nullable();

            // Nullable because perpetual licences never expire. Null means
            // "never expires", never "overdue".
            $table->date('expires_at')->nullable();

            $table->decimal('renewal_cost', 10, 2)->nullable();
            $table->boolean('auto_renew')->default(false);

            // Which email or account owns the subscription. Schools lose track
            // of this and find out the day the person who set it up leaves.
            $table->string('account_reference')->nullable();

            // Covers an OEM licence tied to one machine. A single nullable
            // foreign key, not a many-to-many.
            $table->foreignId('asset_id')->nullable()->constrained()->nullOnDelete();

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('licenses');
    }
};
