<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_entry_lines', function (Blueprint $table) {
            $table->enum('source', ['salary', 'attendance', 'overtime', 'reward', 'allowance', 'manual', 'adjustment'])->change();
        });
    }

    public function down(): void
    {
        Schema::table('payroll_entry_lines', function (Blueprint $table) {
            $table->enum('source', ['salary', 'attendance', 'overtime', 'reward', 'manual', 'adjustment'])->change();
        });
    }
};
