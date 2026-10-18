<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_calendar_days', function (Blueprint $table) {
            if (! Schema::hasColumn('company_calendar_days', 'allowed_user_ids')) {
                // Only meaningful for type="workday": a null/empty value
                // means the override opens that date for every employee
                // (today's behavior); a non-empty list restricts it to
                // those specific employees only — everyone else still
                // sees the date as it would resolve by default (Sunday
                // closed, or whatever the underlying day would be).
                $table->json('allowed_user_ids')->nullable()->after('type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('company_calendar_days', function (Blueprint $table) {
            if (Schema::hasColumn('company_calendar_days', 'allowed_user_ids')) {
                $table->dropColumn('allowed_user_ids');
            }
        });
    }
};
