<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_event_reminders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('user_id');
            $table->string('reminder_type', 30)->default('notification');
            $table->integer('minutes_before')->default(30);
            $table->boolean('is_sent')->default(false);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->foreign('event_id')->references('id')->on('calendar_events')->cascadeOnDelete();
            $table->index('event_id');
            $table->index(['user_id', 'is_sent']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_event_reminders');
    }
};
