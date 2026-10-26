<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'is_allowance_eligible')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            // Defaults true so every existing/new karyawan keeps today's
            // behavior (Tunjangan Kehadiran + Tunjangan Transport applied
            // like normal). HRD turns this off per employee for someone
            // who shouldn't get either allowance — they still get Gaji
            // Pokok and are still subject to Potongan Telat/Alpha.
            $table->boolean('is_allowance_eligible')->default(true)->after('is_payroll_eligible');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'is_allowance_eligible')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_allowance_eligible');
        });
    }
};
