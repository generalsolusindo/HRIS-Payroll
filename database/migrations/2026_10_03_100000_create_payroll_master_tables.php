<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('divisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('code', 30);
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'code']);
        });

        Schema::create('payroll_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('code', 50);
            $table->string('name');
            // Employer contributions never reduce employee take-home pay.
            $table->enum('type', ['earning', 'deduction', 'employer_contribution']);
            $table->enum('category', ['base_salary', 'allowance', 'incentive', 'overtime', 'attendance_reward', 'attendance_deduction', 'insurance', 'tax', 'other']);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'code']);
        });

        Schema::create('payroll_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('code', 50);
            $table->unsignedInteger('version');
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            // Versioned declarative parameters, never executable PHP/SQL.
            $table->json('rules');
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'code', 'version']);
            $table->index(['company_id', 'effective_from']);
        });

        Schema::create('employee_payroll_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('division_id')->constrained()->restrictOnDelete();
            $table->foreignId('position_id')->constrained()->restrictOnDelete();
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->date('employment_started_on');
            $table->date('employment_ended_on')->nullable();
            $table->string('employment_type', 40);
            $table->string('bank_name')->nullable();
            // Store encrypted values through the application, not plaintext.
            $table->text('bank_account_number')->nullable();
            $table->text('bank_account_holder')->nullable();
            $table->text('tax_identity')->nullable();
            $table->text('insurance_identity')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'effective_from']);
            $table->index(['company_id', 'division_id']);
        });

        Schema::create('employee_salary_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_payroll_profile_id')->constrained()->restrictOnDelete();
            $table->foreignId('payroll_component_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 18, 2);
            $table->enum('basis', ['monthly', 'daily', 'hourly', 'fixed']);
            $table->timestamps();
            $table->unique(['employee_payroll_profile_id', 'payroll_component_id'], 'salary_profile_component_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_salary_components');
        Schema::dropIfExists('employee_payroll_profiles');
        Schema::dropIfExists('payroll_policies');
        Schema::dropIfExists('payroll_components');
        Schema::dropIfExists('divisions');
    }
};
