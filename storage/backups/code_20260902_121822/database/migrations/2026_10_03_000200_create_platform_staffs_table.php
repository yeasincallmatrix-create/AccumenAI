<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('platform_staffs')) {
            return;
        }

        Schema::create('platform_staffs', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->default(DB::raw('(UUID())'))->unique('uq_platform_staffs_uuid');
            $table->string('first_name', 60)->default('');
            $table->string('last_name', 60)->default('');
            $table->string('email', 150)->unique('uq_platform_staffs_email');
            $table->string('phone', 20)->nullable();
            $table->string('password_hash', 255);
            $table->string('preferred_language', 10)->default('en');
            $table->longText('preferences')->nullable();
            $table->string('role', 50)->default('support'); // support, finance, verification, technical, content, compliance
            $table->enum('status', ['active', 'suspended', 'inactive'])->default('active');
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->unsignedTinyInteger('failed_login_count')->default(0);
            $table->timestamp('locked_until')->nullable();
            $table->string('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('role');
            $table->index('status');
        });

        Schema::create('platform_staff_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('platform_staff_id');
            $table->unsignedBigInteger('permission_id');
            $table->primary(['platform_staff_id', 'permission_id']);
            $table->foreign('platform_staff_id')->references('id')->on('platform_staffs')->onDelete('cascade');
            $table->foreign('permission_id')->references('id')->on('permissions')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_staff_permissions');
        Schema::dropIfExists('platform_staffs');
    }
};
