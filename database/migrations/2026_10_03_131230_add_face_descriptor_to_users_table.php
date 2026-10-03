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
        Schema::table('users', function (Blueprint $table) {
            // Encrypted (App\Models\User casts this as encrypted:array), so
            // it needs more room than the raw 128-float JSON would take.
            $table->text('face_descriptor')->nullable()->after('status');
            $table->timestamp('face_enrolled_at')->nullable()->after('face_descriptor');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['face_descriptor', 'face_enrolled_at']);
        });
    }
};
