<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_nav_badge_views')) {
            return;
        }

        Schema::create('user_nav_badge_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('badge_key', 60);
            $table->timestamp('last_seen_at');

            $table->unique(['user_id', 'badge_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_nav_badge_views');
    }
};
