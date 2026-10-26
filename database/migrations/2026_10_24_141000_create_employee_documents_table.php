<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('employee_documents')) {
            return;
        }

        Schema::create('employee_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Fixed category (ktp/ktm/ijazah) or 'lainnya' with a free-text
            // label — every upload here is optional, HRD adds whichever
            // documents it has on hand, never a required checklist.
            $table->string('type', 30);
            $table->string('label')->nullable();
            $table->string('file_name');
            $table->string('file_path', 500);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('file_size');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_documents');
    }
};
