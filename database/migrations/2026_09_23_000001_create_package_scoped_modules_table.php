<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('package_scoped_modules')) {
            return;
        }

        Schema::create('package_scoped_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_scope_id')
                ->constrained('package_scopes')
                ->cascadeOnDelete();
            $table->string('module_key', 60);
            $table->boolean('enabled')->default(true);
            $table->string('scope_hash', 120)->nullable();  // set via event
            $table->timestamps();

            $table->unique(
                ['package_scope_id', 'module_key'],
                'uq_scoped_module'
            );
            $table->unique('scope_hash', 'uq_scoped_module_hash');
            $table->index('module_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_scoped_modules');
    }
};
