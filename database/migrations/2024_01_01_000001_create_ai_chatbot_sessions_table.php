<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_shopbot_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('session_id', 64)->unique();
            $table->string('user_ip', 45)->nullable();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedInteger('message_count')->default(0);
            $table->timestamp('last_active_at')->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_shopbot_sessions');
    }
};
