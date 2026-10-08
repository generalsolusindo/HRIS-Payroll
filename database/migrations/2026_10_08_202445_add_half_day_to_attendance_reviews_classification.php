<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_reviews', function (Blueprint $table) {
            $table->enum('classification', [
                'unresolved', 'present', 'half_day', 'leave', 'sick',
                'permission', 'business_trip', 'absent', 'rest_day', 'holiday',
            ])->default('unresolved')->change();
        });
    }

    public function down(): void
    {
        Schema::table('attendance_reviews', function (Blueprint $table) {
            $table->enum('classification', [
                'unresolved', 'present', 'leave', 'sick',
                'permission', 'business_trip', 'absent', 'rest_day', 'holiday',
            ])->default('unresolved')->change();
        });
    }
};
