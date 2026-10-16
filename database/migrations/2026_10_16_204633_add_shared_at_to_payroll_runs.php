<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lets HRD opt a draft run into employee pre-review ("Pratinjau Gaji")
     * before submitting it to the Manager — the point is to let employees
     * catch a mistake while it can still be corrected, not after the
     * numbers are locked in. Null means never shared; once set, the
     * employee keeps seeing their entry's live figures through the rest
     * of the run's lifecycle (not just while still draft).
     */
    public function up(): void
    {
        Schema::table('payroll_runs', function (Blueprint $table) {
            if (! Schema::hasColumn('payroll_runs', 'shared_at')) {
                $table->timestamp('shared_at')->nullable()->after('submitted_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payroll_runs', function (Blueprint $table) {
            $table->dropColumn('shared_at');
        });
    }
};
