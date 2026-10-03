<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            // Null means "not checked" (the employee hasn't enrolled a
            // face yet — face verification is optional). A mismatch never
            // blocks attendance; it's flagged here for HRD to review, GPS
            // + photo stay the authoritative check.
            $table->boolean('clock_in_face_match')->nullable()->after('clock_in_status');
            $table->decimal('clock_in_face_distance', 6, 4)->nullable()->after('clock_in_face_match');

            $table->boolean('clock_out_face_match')->nullable()->after('clock_out_status');
            $table->decimal('clock_out_face_distance', 6, 4)->nullable()->after('clock_out_face_match');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn([
                'clock_in_face_match',
                'clock_in_face_distance',
                'clock_out_face_match',
                'clock_out_face_distance',
            ]);
        });
    }
};
