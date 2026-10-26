<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'probation_ends_on')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            // Separate from employment_type (which is the eventual/target
            // contract kind — Tetap/Kontrak/dll) — this tracks whether
            // someone who already has a working account is currently in
            // their trial period, and when that trial is due to end.
            // Non-null means "currently on probation"; no extra boolean.
            $table->date('probation_ends_on')->nullable()->after('employment_started_on');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'probation_ends_on')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('probation_ends_on');
        });
    }
};
