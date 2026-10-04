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
        Schema::create('face_verification_receipts', function (Blueprint $table) {
            $table->id();
            // The opaque bearer value returned to the client — never the
            // auto-increment id, so a receipt can't be enumerated/guessed.
            $table->string('token', 64)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Binds the receipt to the session that requested it, so a
            // leaked token is useless from a different session.
            $table->string('session_id');
            $table->string('action', 10);
            // Client-supplied UUID, purely for correlating logs/debugging.
            $table->string('attempt_id');
            // Snapshot of the face_enrolled_at the match was checked
            // against — if the user re-enrolls a different face before
            // the final submit, this no longer matches and the receipt
            // is treated as stale even if not yet expired.
            $table->timestamp('face_enrolled_at_snapshot')->nullable();
            $table->string('image_hash', 64)->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'action']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('face_verification_receipts');
    }
};
