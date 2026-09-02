<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id')->nullable()->index();
            $table->foreign('institute_id')->references('id')->on('institutes')->nullOnDelete();
            $table->string('event', 100);
            $table->enum('channel', ['in_app', 'email', 'sms']);
            $table->string('language', 10)->default('en');
            $table->string('name', 120);
            $table->string('subject', 190)->nullable();
            $table->text('body');
            $table->json('variables')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // institute_id NULL = industry-neutral default template usable by any institute.
            $table->unique(['institute_id', 'event', 'channel', 'language'], 'notification_templates_scope_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_templates');
    }
};
