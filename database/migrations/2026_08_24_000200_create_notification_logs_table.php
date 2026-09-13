<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id')->nullable()->index();
            $table->foreign('institute_id')->references('id')->on('institutes')->nullOnDelete();
            $table->unsignedBigInteger('template_id')->nullable()->index();
            $table->foreign('template_id')->references('id')->on('notification_templates')->nullOnDelete();
            // Links the canonical log row to the existing in-app notifications row (if channel = in_app).
            $table->unsignedBigInteger('notification_id')->nullable()->index();
            $table->string('event', 100);
            $table->enum('recipient_type', ['institute_user', 'platform_admin', 'student', 'external_email', 'external_phone'])->nullable();
            $table->unsignedBigInteger('recipient_id')->nullable();
            $table->string('recipient_contact', 255)->nullable();
            $table->enum('channel', ['in_app', 'email', 'sms']);
            $table->enum('status', ['queued', 'sending', 'sent', 'failed', 'skipped'])->default('queued');
            $table->string('subject', 190)->nullable();
            $table->text('body')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->unsignedTinyInteger('retry_count')->default(0);
            $table->unsignedTinyInteger('max_retries')->default(0);
            $table->string('provider', 60)->nullable();
            $table->string('provider_message_id', 190)->nullable();
            $table->text('provider_response')->nullable();
            $table->text('error')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['status', 'retry_count']);
            $table->index(['recipient_type', 'recipient_id']);
            $table->index(['institute_id', 'event']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};
