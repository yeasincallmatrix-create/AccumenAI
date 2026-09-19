<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tenant_access_denials')) {
            return;
        }

        Schema::create('tenant_access_denials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')
                ->constrained('institutes')
                ->cascadeOnDelete();
            $table->enum('deny_type', ['module', 'feature']);
            $table->string('deny_key', 100);
            $table->unsignedBigInteger('denied_by');
            $table->timestamp('denied_at')->useCurrent();
            $table->timestamp('expires_at')->nullable();
            $table->string('reason', 255);
            $table->enum('status', ['active', 'lifted', 'expired'])
                ->default('active');
            $table->timestamp('lifted_at')->nullable();
            $table->unsignedBigInteger('lifted_by')->nullable();
            $table->timestamps();

            $table->unique(
                ['institute_id', 'deny_type', 'deny_key', 'status'],
                'uniq_active_deny'
            );
            $table->index('institute_id');
            $table->index('expires_at');

            $table->foreign('denied_by')
                ->references('id')->on('platform_admins')->cascadeOnDelete();
            $table->foreign('lifted_by')
                ->references('id')->on('platform_admins')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_access_denials');
    }
};
