<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Guarded like the other batch migrations in this project: MySQL
        // doesn't wrap DDL in a transaction either, so a partial failure
        // here (and a retry) must not blow up on "column already exists".
        Schema::table('payroll_components', function (Blueprint $table) {
            if (! Schema::hasColumn('payroll_components', 'calculation_mode')) {
                $table->string('calculation_mode', 20)->default('profile')->after('type');
            }
            if (! Schema::hasColumn('payroll_components', 'calculation_rule')) {
                $table->string('calculation_rule', 60)->nullable()->after('calculation_mode');
            }
        });

        DB::table('payroll_components')
            ->whereIn('code', [
                'ATTENDANCE_LATE',
                'ATTENDANCE_ABSENCE',
                'ATTENDANCE_PERFECT',
                'OVERTIME',
                'ATTENDANCE_ALLOWANCE',
            ])
            ->update(['calculation_mode' => 'system', 'calculation_rule' => 'built_in']);

        Schema::table('divisions', function (Blueprint $table) {
            if (! Schema::hasColumn('divisions', 'transport_allowance_per_attendance_day')) {
                $table->decimal('transport_allowance_per_attendance_day', 18, 2)
                    ->default(0)
                    ->after('daily_attendance_allowance');
            }
        });
    }

    public function down(): void
    {
        Schema::table('divisions', function (Blueprint $table) {
            if (Schema::hasColumn('divisions', 'transport_allowance_per_attendance_day')) {
                $table->dropColumn('transport_allowance_per_attendance_day');
            }
        });

        Schema::table('payroll_components', function (Blueprint $table) {
            $drop = array_filter(
                ['calculation_mode', 'calculation_rule'],
                fn (string $column) => Schema::hasColumn('payroll_components', $column),
            );
            if ($drop !== []) {
                $table->dropColumn($drop);
            }
        });
    }
};
