<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Whether an inspection counts towards the schedule. One recorded from a
     * phone that was not set up for safety checks is kept — what was found is
     * still worth knowing — but it does not reset the clock.
     *
     * Every inspection recorded before this column existed counted at the
     * time, so it is marked as counted rather than rewritten after the fact.
     */
    public function up(): void
    {
        Schema::table('inspections', function (Blueprint $table) {
            $table->boolean('counted')->default(false)->after('result');
        });

        DB::table('inspections')->update(['counted' => true]);
    }

    public function down(): void
    {
        Schema::table('inspections', function (Blueprint $table) {
            $table->dropColumn('counted');
        });
    }
};
