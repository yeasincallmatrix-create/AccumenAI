<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id')->nullable()->index();
            $table->foreign('institute_id')->references('id')->on('institutes')->nullOnDelete();
            $table->enum('recipient_type', ['institute_user', 'platform_admin', 'student', 'external_email', 'external_phone']);
            $table->unsignedBigInteger('recipient_id');
            // NULL = applies to every event.
            $table->string('event', 100)->nullable();
            $table->enum('channel', ['in_app', 'email', 'sms']);
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->unique(['recipient_type', 'recipient_id', 'event', 'channel'], 'notification_preferences_scope_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};
