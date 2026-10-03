<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained()->restrictOnDelete();
            $table->enum('action', ['submitted', 'revision_requested', 'approved', 'locked', 'reopen_requested', 'reopen_approved', 'reopen_rejected', 'superseded', 'cancelled']);
            $table->string('from_status', 30);
            $table->string('to_status', 30);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('actor_snapshot');
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['payroll_run_id', 'created_at']);
        });

        Schema::create('payroll_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_entry_id')->constrained()->restrictOnDelete();
            $table->uuid('idempotency_key')->unique();
            $table->decimal('amount', 18, 2);
            $table->enum('method', ['bank_transfer', 'cash', 'other']);
            $table->enum('status', ['pending', 'confirmed', 'failed', 'voided'])->default('pending');
            $table->string('reference')->nullable();
            $table->string('proof_path')->nullable();
            $table->text('destination_snapshot')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index(['payroll_entry_id', 'status']);
        });

        Schema::create('payroll_payslips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_entry_id')->constrained()->restrictOnDelete();
            $table->string('number', 80)->unique();
            $table->unsignedInteger('version');
            $table->enum('status', ['draft', 'published', 'superseded'])->default('draft');
            $table->string('file_path')->nullable();
            $table->char('file_sha256', 64)->nullable();
            $table->json('snapshot');
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['payroll_entry_id', 'version']);
        });

        Schema::create('payroll_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('payroll_run_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('attendance_review_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('actor_snapshot');
            $table->string('entity_type', 80);
            $table->unsignedBigInteger('entity_id');
            $table->string('action', 80);
            $table->json('before_values')->nullable();
            $table->json('after_values')->nullable();
            $table->text('reason');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['entity_type', 'entity_id']);
            $table->index(['company_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_audit_logs');
        Schema::dropIfExists('payroll_payslips');
        Schema::dropIfExists('payroll_payments');
        Schema::dropIfExists('payroll_approvals');
    }
};
