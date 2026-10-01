<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Accounts can now be managed from Manage → Users, so an account needs a state
 * beyond "exists". No role column: every account is an admin (spec §3, §19).
 *
 * - deactivated_at: switched off, never deleted. Reports, inspector phones and
 *   the audit log point at these rows, and deleting one would orphan that
 *   history.
 * - password_set_at: when the person last chose their own password. Null means
 *   they are still waiting to choose one with a once-only link, after the
 *   account was made or its password was reset.
 * - last_login_at: so a forgotten or misused account stands out.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('deactivated_at')->nullable()->after('remember_token');
            $table->timestamp('password_set_at')->nullable()->after('deactivated_at');
            $table->timestamp('last_login_at')->nullable()->after('password_set_at');
        });

        // Accounts made before this existed were given a real password on the
        // command line; they are not waiting for anything. A row without a
        // created_at must not come out of this null, or it would be locked out.
        DB::table('users')->update(['password_set_at' => DB::raw('COALESCE(created_at, CURRENT_TIMESTAMP)')]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['deactivated_at', 'password_set_at', 'last_login_at']);
        });
    }
};
