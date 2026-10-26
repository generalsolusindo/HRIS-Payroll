<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'contract_ends_on')) {
                $table->date('contract_ends_on')->nullable()->after('employment_started_on');
            }
            if (! Schema::hasColumn('users', 'employment_ended_on')) {
                $table->date('employment_ended_on')->nullable()->after('contract_ends_on');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'employment_ended_on')) {
                $table->dropColumn('employment_ended_on');
            }
            if (Schema::hasColumn('users', 'contract_ends_on')) {
                $table->dropColumn('contract_ends_on');
            }
        });
    }
};
