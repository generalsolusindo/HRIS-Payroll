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
            $table->foreignId('company_id')->after('id')->constrained()->restrictOnDelete();
            $table->foreignId('position_id')->after('company_id')->constrained()->restrictOnDelete();
            $table->string('employee_number')->nullable()->unique()->after('position_id');
            $table->string('phone')->nullable()->after('email');
            $table->string('role')->default('karyawan')->after('phone');
            $table->string('status')->default('pending')->after('role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_id');
            $table->dropConstrainedForeignId('position_id');
            $table->dropColumn(['employee_number', 'phone', 'role', 'status']);
        });
    }
};
