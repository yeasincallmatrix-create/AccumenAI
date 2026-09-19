<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tenant_access_grants')) {
            return;
        }

        Schema::create('tenant_access_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')
                ->constrained('institutes')
                ->cascadeOnDelete();
            $table->enum('grant_type', ['module', 'feature', 'tier']);
            $table->string('grant_key', 100);
            $table->unsignedBigInteger('granted_by');
            $table->timestamp('granted_at')->useCurrent();
            $table->timestamp('expires_at')->nullable();
            $table->string('reason', 255)->nullable();
            $table->enum('status', ['active', 'revoked', 'expired', 'superseded'])
                ->default('active');
            $table->timestamp('revoked_at')->nullable();
            $table->unsignedBigInteger('revoked_by')->nullable();
            $table->timestamps();

            $table->index('institute_id');
            $table->index('status');
            $table->index('expires_at');

            $table->foreign('granted_by')
                ->references('id')->on('platform_admins')->cascadeOnDelete();
            $table->foreign('revoked_by')
                ->references('id')->on('platform_admins')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_access_grants');
    }
};
