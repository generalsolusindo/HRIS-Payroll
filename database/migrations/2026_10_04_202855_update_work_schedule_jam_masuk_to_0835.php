<?php

use App\Models\WorkSchedule;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Business decision: the clock-in deadline for "tepat waktu" moves from
     * 08:30 to 08:35. Only rows still at the old default are touched, so a
     * company whose schedule has since been customized to something else
     * isn't silently overwritten.
     */
    public function up(): void
    {
        WorkSchedule::where('jam_masuk', '08:30:00')->update(['jam_masuk' => '08:35:00']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        WorkSchedule::where('jam_masuk', '08:35:00')->update(['jam_masuk' => '08:30:00']);
    }
};
