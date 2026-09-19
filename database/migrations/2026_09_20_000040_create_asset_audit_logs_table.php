<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('asset_audit_logs')) {
            return;
        }

        Schema::create('asset_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('asset_id')->nullable();
            $table->string('event');
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->longText('old_value')->nullable();
            $table->longText('new_value')->nullable();
            $table->string('reason')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::table('asset_audit_logs', function (Blueprint $table) {
            $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('restrict');
            $table->foreign('asset_id')->references('id')->on('fixed_assets')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_audit_logs');
    }
};
