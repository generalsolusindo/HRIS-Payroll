<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('divisions', function (Blueprint $table) {
            if (! Schema::hasColumn('divisions', 'base_salary')) {
                $table->decimal('base_salary', 18, 2)->default(0)->after('name');
            }
        });

        Schema::table('divisions', function (Blueprint $table) {
            $drop = array_filter(
                ['daily_attendance_allowance', 'transport_allowance_per_attendance_day'],
                fn (string $column) => Schema::hasColumn('divisions', $column),
            );
            if ($drop !== []) {
                $table->dropColumn($drop);
            }
        });
    }

    public function down(): void
    {
        Schema::table('divisions', function (Blueprint $table) {
            if (! Schema::hasColumn('divisions', 'daily_attendance_allowance')) {
                $table->decimal('daily_attendance_allowance', 18, 2)->default(0);
            }
            if (! Schema::hasColumn('divisions', 'transport_allowance_per_attendance_day')) {
                $table->decimal('transport_allowance_per_attendance_day', 18, 2)->default(0);
            }
        });

        Schema::table('divisions', function (Blueprint $table) {
            if (Schema::hasColumn('divisions', 'base_salary')) {
                $table->dropColumn('base_salary');
            }
        });
    }
};
