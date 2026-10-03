<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('code', 40);
            $table->string('name');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->date('attendance_starts_on');
            $table->date('attendance_ends_on');
            $table->date('payment_due_on')->nullable();
            $table->char('currency', 3)->default('IDR');
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->timestamps();
            $table->unique(['company_id', 'code']);
            $table->unique(['company_id', 'starts_on', 'ends_on'], 'payroll_period_dates_unique');
        });

        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_period_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->foreignId('supersedes_run_id')->nullable()->constrained('payroll_runs')->restrictOnDelete();
            $table->foreignId('payroll_policy_id')->constrained()->restrictOnDelete();
            $table->json('policy_snapshot');
            $table->json('company_snapshot');
            $table->enum('status', ['draft', 'submitted', 'revision_requested', 'approved', 'locked', 'partially_paid', 'paid', 'superseded', 'cancelled'])->default('draft');
            $table->unsignedInteger('employee_count')->default(0);
            $table->decimal('gross_total', 18, 2)->default(0);
            $table->decimal('deduction_total', 18, 2)->default(0);
            $table->decimal('reward_total', 18, 2)->default(0);
            $table->decimal('employer_contribution_total', 18, 2)->default(0);
            $table->decimal('net_total', 18, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->text('revision_reason')->nullable();
            $table->timestamps();
            $table->unique(['payroll_period_id', 'version']);
            $table->index(['payroll_period_id', 'status']);
        });

        Schema::create('payroll_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_payroll_profile_id')->constrained()->restrictOnDelete();
            $table->foreignId('division_id')->constrained()->restrictOnDelete();
            $table->json('employee_snapshot');
            $table->string('division_name');
            $table->string('position_name');
            $table->json('attendance_snapshot');
            $table->json('compensation_snapshot');
            // Encrypted serialized bank destination; never include in public JSON snapshots.
            $table->text('payment_destination')->nullable();
            $table->decimal('gross_amount', 18, 2)->default(0);
            $table->decimal('deduction_amount', 18, 2)->default(0);
            $table->decimal('reward_amount', 18, 2)->default(0);
            $table->decimal('employer_contribution_amount', 18, 2)->default(0);
            $table->decimal('net_amount', 18, 2)->default(0);
            $table->text('note')->nullable();
            $table->timestamps();
            $table->unique(['payroll_run_id', 'user_id']);
            $table->index(['payroll_run_id', 'division_id']);
        });

        Schema::create('payroll_entry_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_entry_id')->constrained()->restrictOnDelete();
            $table->foreignId('payroll_component_id')->constrained()->restrictOnDelete();
            // Stable key within an entry prevents duplicate calculation/retry lines.
            $table->string('line_key', 100);
            $table->string('component_code', 50);
            $table->string('component_name');
            $table->enum('type', ['earning', 'deduction', 'employer_contribution']);
            $table->string('category', 40);
            $table->decimal('quantity', 12, 4)->default(1);
            $table->decimal('rate', 18, 4)->default(0);
            $table->decimal('amount', 18, 2);
            $table->enum('source', ['salary', 'attendance', 'overtime', 'reward', 'manual', 'adjustment']);
            $table->json('calculation_snapshot');
            $table->text('adjustment_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['payroll_entry_id', 'line_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_entry_lines');
        Schema::dropIfExists('payroll_entries');
        Schema::dropIfExists('payroll_runs');
        Schema::dropIfExists('payroll_periods');
    }
};
