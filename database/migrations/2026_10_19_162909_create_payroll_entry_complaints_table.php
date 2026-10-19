<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payroll_entry_complaints')) {
            return;
        }

        Schema::create('payroll_entry_complaints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_entry_id')->constrained()->cascadeOnDelete();
            // Denormalized against the entry's own user_id — the entry
            // could in principle be deleted (see period deletion requests)
            // while the complaint's own history should still make sense.
            $table->foreignId('user_id')->constrained();
            $table->text('message');
            $table->string('status', 20)->default('new');
            $table->text('hrd_note')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_entry_complaints');
    }
};
