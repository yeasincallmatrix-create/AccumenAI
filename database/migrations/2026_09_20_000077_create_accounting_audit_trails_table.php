<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('accounting_audit_trails')) {
            return;
        }

        Schema::create('accounting_audit_trails', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->enum('actor_type', ['user', 'system', 'ai', 'cron', 'import'])->default('user');
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->enum('action', ['create', 'update', 'delete', 'post', 'reverse', 'void', 'waive', 'lock', 'close', 'reopen', 'import', 'migrate', 'export', 'recurring_fee_generated']);
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id');
            $table->longText('before_payload')->nullable();
            $table->longText('after_payload')->nullable();
            $table->string('ip')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::table('accounting_audit_trails', function (Blueprint $table) {
            $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('restrict');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_audit_trails');
    }
};
