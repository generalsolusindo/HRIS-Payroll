<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_calendar_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->date('date');
            $table->enum('type', ['workday', 'rest_day', 'holiday']);
            $table->string('description')->nullable();
            $table->foreignId('work_schedule_id')->nullable()->constrained()->nullOnDelete();
            $table->json('schedule_snapshot')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'date']);
        });

        Schema::create('attendance_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->date('date');
            $table->foreignId('attendance_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('status', ['draft', 'validated'])->default('draft');
            // Missing punches remain unresolved until reviewed, never automatic alpha.
            $table->enum('classification', ['unresolved', 'present', 'leave', 'sick', 'permission', 'business_trip', 'absent', 'rest_day', 'holiday'])->default('unresolved');
            $table->dateTime('clock_in_at')->nullable();
            $table->dateTime('clock_out_at')->nullable();
            $table->unsignedInteger('scheduled_minutes')->default(0);
            $table->unsignedInteger('worked_minutes')->default(0);
            $table->unsignedInteger('late_minutes')->default(0);
            $table->unsignedInteger('early_departure_minutes')->default(0);
            $table->unsignedInteger('excused_minutes')->default(0);
            $table->json('source_snapshot');
            // Array of approved leave IDs and their values, including partial-day leave.
            $table->json('leave_snapshot')->nullable();
            $table->json('schedule_snapshot');
            $table->text('correction_reason')->nullable();
            $table->string('evidence_path')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'date']);
            $table->index(['company_id', 'date', 'status']);
        });

        Schema::create('overtime_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->unsignedInteger('break_minutes')->default(0);
            $table->unsignedInteger('approved_minutes')->nullable();
            $table->text('reason');
            $table->enum('status', ['draft', 'submitted', 'approved', 'rejected', 'cancelled'])->default('draft');
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'status', 'starts_at']);
            $table->index(['user_id', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('overtime_requests');
        Schema::dropIfExists('attendance_reviews');
        Schema::dropIfExists('company_calendar_days');
    }
};
