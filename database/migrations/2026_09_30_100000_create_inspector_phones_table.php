<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A phone the director has enrolled to record official safety checks. It
     * is a fact with its own lifecycle — invited, set up, withdrawn — that
     * inspection results depend on, so it gets a table, like the device block
     * list does.
     *
     * Without it, anyone who scans a fire extinguisher can mark it "fine" and
     * reset its inspection clock: a legal-weight record silenced by an
     * anonymous tap. With it, a check only counts from a phone the director
     * set up in person, and nobody needs a password.
     */
    public function up(): void
    {
        Schema::create('inspector_phones', function (Blueprint $table) {
            $table->id();
            $table->string('label', 80);
            // Not unique: a withdrawn phone keeps its token for the record,
            // and the same phone may be set up again later.
            $table->string('device_token', 64)->nullable()->index();
            $table->timestamp('invite_expires_at')->nullable();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspector_phones');
    }
};
