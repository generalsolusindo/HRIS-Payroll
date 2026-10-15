<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Tanggal Mulai Kerja" belongs on the user as a one-time, unchanging
     * fact captured once at Manajemen User — not re-typed on every payroll
     * profile edit. Backfilled here from each user's most recent payroll
     * profile (if one exists); users without a profile yet are left null
     * until HRD fills it in via Manajemen User.
     */
    public function up(): void
    {
        // SQLite doesn't wrap schema changes in a transaction, so a retry
        // after a failure anywhere later in this migration (or an earlier
        // one in the same deploy) must not re-attempt an ALTER that
        // already committed — see the 2026_10_15_130040 migration for the
        // "duplicate column" failure this guards against.
        if (! Schema::hasColumn('users', 'employment_started_on')) {
            Schema::table('users', function (Blueprint $table) {
                $table->date('employment_started_on')->nullable()->after('employment_type');
            });
        }

        $seen = [];
        DB::table('employee_payroll_profiles')
            ->orderByDesc('effective_from')
            ->select('user_id', 'employment_started_on')
            ->get()
            ->each(function (object $profile) use (&$seen) {
                if (isset($seen[$profile->user_id])) {
                    return;
                }
                $seen[$profile->user_id] = true;

                DB::table('users')->where('id', $profile->user_id)
                    ->update(['employment_started_on' => $profile->employment_started_on]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('employment_started_on');
        });
    }
};
