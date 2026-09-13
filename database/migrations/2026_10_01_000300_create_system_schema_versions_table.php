<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_schema_versions', function (Blueprint $table) {
            $table->id();
            $table->string('version', 50);
            $table->string('database_version', 50)->nullable();
            $table->string('laravel_version', 50)->nullable();
            $table->unsignedInteger('migration_count')->default(0);
            $table->string('checksum', 64)->nullable();
            $table->timestamp('installed_at')->useCurrent();
            $table->timestamps();

            $table->index('version');
            $table->index('installed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_schema_versions');
    }
};
