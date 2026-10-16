<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * users.position_id was already made nullable (see the
     * 2026_10_15_133837 migration) because Manajemen User no longer sets
     * it — Division replaced Position. But PayrollProfileController still
     * copies $user->position_id onto the profile, and this column was
     * never updated to match, so saving a payroll profile for any
     * employee without a position_id (every employee created since) hit
     * a NOT NULL violation. position_name is already read from the
     * division relation everywhere (see PayrollRunController), so nothing
     * downstream depends on this column being non-null.
     */
    public function up(): void
    {
        Schema::table('employee_payroll_profiles', function (Blueprint $table) {
            $table->foreignId('position_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('employee_payroll_profiles', function (Blueprint $table) {
            $table->foreignId('position_id')->nullable(false)->change();
        });
    }
};
