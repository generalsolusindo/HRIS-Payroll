<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * default_base_salary was meant to auto-fill Gaji Pokok when HRD set up
     * an employee's payroll profile, but that auto-fill feature was removed
     * (see the Kategori cleanup earlier this project) and nothing has read
     * this column since — HRD was still being forced to fill in a number
     * with zero effect on payroll.
     */
    public function up(): void
    {
        Schema::table('divisions', function (Blueprint $table) {
            if (Schema::hasColumn('divisions', 'default_base_salary')) {
                $table->dropColumn('default_base_salary');
            }
        });
    }

    public function down(): void
    {
        Schema::table('divisions', function (Blueprint $table) {
            $table->decimal('default_base_salary', 18, 2)->default(0)->after('is_active');
        });
    }
};
