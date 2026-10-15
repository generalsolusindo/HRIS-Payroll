<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Category turned out to carry almost no real weight — the payroll
     * calculation only ever branches on `type` (earning/deduction/
     * employer_contribution); category was just a display grouping, never
     * shown anywhere in the UI, with the one functional exception (an
     * auto-fill of Gaji Pokok keyed off category='base_salary') removed
     * alongside this. Name + Jenis is enough.
     */
    public function up(): void
    {
        // Guarded so a retry after a partial failure elsewhere in the
        // same deploy doesn't re-attempt a drop that already committed —
        // SQLite doesn't wrap schema changes in a transaction (see the
        // 2026_10_15_130040 migration for the failure this prevents).
        Schema::table('payroll_components', function (Blueprint $table) {
            if (Schema::hasColumn('payroll_components', 'category')) {
                $table->dropColumn('category');
            }
        });

        Schema::table('payroll_entry_lines', function (Blueprint $table) {
            if (Schema::hasColumn('payroll_entry_lines', 'category')) {
                $table->dropColumn('category');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payroll_components', function (Blueprint $table) {
            $table->enum('category', ['base_salary', 'allowance', 'incentive', 'overtime', 'attendance_reward', 'attendance_deduction', 'insurance', 'tax', 'other'])
                ->default('other')->after('type');
        });

        Schema::table('payroll_entry_lines', function (Blueprint $table) {
            $table->string('category', 40)->default('other')->after('type');
        });
    }
};
