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
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_number', 50)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('type', 20)->index();
            $table->date('leave_date')->index();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->time('estimated_arrival')->nullable();
            $table->decimal('duration', 5, 2)->nullable();

            $table->text('reason')->nullable();
            $table->boolean('emergency')->default(false);
            $table->text('emergency_reason')->nullable();
            $table->boolean('contactable')->nullable();

            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->text('approval_note')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->foreignId('hrd_processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('hrd_processed_at')->nullable();
            $table->string('hrd_decision', 20)->nullable();
            $table->text('hrd_note')->nullable();

            $table->string('email_status', 20)->default('pending');
            $table->timestamp('email_sent_at')->nullable();
            $table->text('email_error')->nullable();

            $table->string('telegram_status', 20)->default('pending');
            $table->timestamp('telegram_sent_at')->nullable();
            $table->text('telegram_error')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_requests');
    }
};
