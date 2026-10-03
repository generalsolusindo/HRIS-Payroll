<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Keep stricter existing radii; never enlarge an office boundary.
        DB::table('office_locations')->where('radius_meter', '>', 20)
            ->update(['radius_meter' => 20]);
    }

    public function down(): void
    {
        // Previous per-office settings are unknown. Do not invent larger radii
        // when rolling back; restore from an explicit administrator decision.
    }
};
