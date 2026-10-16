<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PayrollRunController::share() logs itself here as a 'shared_for_review'
     * approval entry (from_status === to_status === 'draft', since sharing
     * doesn't change the run's status) — the enum needs the value before
     * that insert can succeed.
     */
    public function up(): void
    {
        Schema::table('payroll_approvals', function (Blueprint $table) {
            $table->enum('action', [
                'submitted', 'revision_requested', 'approved', 'locked',
                'reopen_requested', 'reopen_approved', 'reopen_rejected',
                'superseded', 'cancelled', 'shared_for_review',
            ])->change();
        });
    }

    public function down(): void
    {
        Schema::table('payroll_approvals', function (Blueprint $table) {
            $table->enum('action', [
                'submitted', 'revision_requested', 'approved', 'locked',
                'reopen_requested', 'reopen_approved', 'reopen_rejected',
                'superseded', 'cancelled',
            ])->change();
        });
    }
};
