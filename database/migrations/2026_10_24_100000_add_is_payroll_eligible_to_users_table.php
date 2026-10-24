<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'is_payroll_eligible')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            // Defaults true so every existing/new karyawan keeps today's
            // behavior (expected in every payroll run) — HRD turns this
            // off only for someone genuinely paid outside the system
            // (e.g. an unpaid intern/volunteer), per employee.
            $table->boolean('is_payroll_eligible')->default(true)->after('employment_started_on');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'is_payroll_eligible')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_payroll_eligible');
        });
    }
};
