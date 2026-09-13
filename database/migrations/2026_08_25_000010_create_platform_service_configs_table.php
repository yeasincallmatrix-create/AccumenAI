<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_service_configs', function (Blueprint $table) {
            $table->id();
            $table->string('service', 50)->comment('email, sms, payment, storage, maps, ai, queue etc');
            $table->string('provider', 50)->nullable();
            $table->string('key', 100)->comment('config key within service');
            $table->text('value')->nullable();
            $table->boolean('is_encrypted')->default(false);
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->unique(['service', 'provider', 'key']);
            $table->index(['service', 'is_enabled']);
        });

        Schema::create('platform_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->nullable()->constrained('platform_admins')->nullOnDelete();
            $table->string('section', 50);
            $table->string('setting_key', 150);
            $table->string('action', 30);
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['section', 'created_at']);
            $table->index(['admin_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_audit_logs');
        Schema::dropIfExists('platform_service_configs');
    }
};
