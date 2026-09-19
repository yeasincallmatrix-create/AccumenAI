<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('lab_device_credentials')) {
            return;
        }

        Schema::create('lab_device_credentials', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('analyzer_id');

            $table->string('token_hash', 64); // SHA-256 or Hash::make output
            $table->string('token_prefix', 12); // First 8-12 chars for lookup
            $table->string('name', 100)->nullable(); // e.g., "Gateway PC #1"

            $table->json('abilities')->nullable(); // ["result:post", "worklist:get", "health:post"]

            $table->timestamp('rotated_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_used_at')->nullable();

            $table->string('last_ip', 45)->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('cascade');
            $table->foreign('analyzer_id')->references('id')->on('lab_analyzers')->onDelete('cascade');

            $table->unique('analyzer_id', 'uniq_device_credential_analyzer');
            $table->index('token_prefix');
            $table->index(['institute_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_device_credentials');
    }
};
