<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_seed_versions', function (Blueprint $table) {
            $table->id();
            $table->string('seed_name', 100);
            $table->string('version', 50)->default('1');
            $table->string('checksum', 64)->nullable();
            $table->timestamp('executed_at')->useCurrent();
            $table->timestamps();

            $table->unique(['seed_name', 'version']);
            $table->index('seed_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_seed_versions');
    }
};
