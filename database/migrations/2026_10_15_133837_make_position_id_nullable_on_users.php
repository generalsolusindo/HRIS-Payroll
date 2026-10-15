<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * users.division_id is the new authoritative field (see the
     * 2026_10_15_130040 migration) — position_id stays on the table for
     * now as historical data, but new/edited users no longer need to set
     * it, so the NOT NULL constraint has to go.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('position_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('position_id')->nullable(false)->change();
        });
    }
};
