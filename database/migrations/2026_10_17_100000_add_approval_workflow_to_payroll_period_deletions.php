<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('payroll_periods', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->index();
        });

        Schema::create('payroll_period_deletion_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_period_id')->constrained()->restrictOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('requested_by_snapshot');
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('decided_by_snapshot')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('reason');
            $table->text('decision_note')->nullable();
            $table->timestamp('requested_at')->useCurrent();
            $table->timestamp('decided_at')->nullable();
            $table->index(['status', 'requested_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_period_deletion_requests');

        Schema::table('payroll_periods', function (Blueprint $table) {
            $table->dropIndex(['archived_at']);
            $table->dropColumn('archived_at');
        });
    }
};