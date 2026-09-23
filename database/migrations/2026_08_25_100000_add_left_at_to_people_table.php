<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The day somebody stopped being at the school.
     *
     * Retention deletes a person a year after this date, so the rule needs a
     * substrate you can query, index and see on the record. Deriving it from
     * the audit log would couple irreversible deletion to log completeness: a
     * seeded row, an import or a bug in the deactivation path would mean the
     * rule silently never fires, with no signal that it did not. The log is
     * evidence, not an operational source for destructive work.
     *
     * Nobody fills this in by hand — PersonObserver sets it when someone is
     * deactivated and clears it when they come back.
     */
    public function up(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->date('left_at')->nullable()->after('active')->index();
        });
    }

    public function down(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->dropIndex(['left_at']);
            $table->dropColumn('left_at');
        });
    }
};
