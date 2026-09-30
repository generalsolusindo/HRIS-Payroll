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
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('tanggal');

            $table->dateTime('clock_in_time')->nullable();
            $table->decimal('clock_in_latitude', 10, 7)->nullable();
            $table->decimal('clock_in_longitude', 10, 7)->nullable();
            $table->unsignedInteger('clock_in_accuracy_meter')->nullable();
            $table->unsignedInteger('clock_in_distance_meter')->nullable();
            $table->string('clock_in_photo_path')->nullable();
            $table->string('clock_in_status')->nullable();

            $table->dateTime('clock_out_time')->nullable();
            $table->decimal('clock_out_latitude', 10, 7)->nullable();
            $table->decimal('clock_out_longitude', 10, 7)->nullable();
            $table->unsignedInteger('clock_out_accuracy_meter')->nullable();
            $table->unsignedInteger('clock_out_distance_meter')->nullable();
            $table->string('clock_out_photo_path')->nullable();
            $table->string('clock_out_status')->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'tanggal']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
