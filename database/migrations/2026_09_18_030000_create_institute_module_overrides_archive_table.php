<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institute_module_overrides_archive', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('original_id');
            $table->unsignedBigInteger('institute_id');
            $table->string('module_key', 60);
            $table->tinyInteger('enabled');
            $table->unsignedBigInteger('overridden_by')->nullable();
            $table->string('reason', 255)->nullable();
            $table->timestamp('archived_at')->useCurrent();
            $table->unsignedBigInteger('archived_by')->nullable();
            $table->string('archive_reason', 60)->nullable();
            $table->unsignedBigInteger('old_package_id')->nullable();
            $table->unsignedBigInteger('new_package_id')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->index('institute_id', 'idx_institute');
            $table->index('archived_at', 'idx_archived_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('institute_module_overrides_archive');
    }
};
