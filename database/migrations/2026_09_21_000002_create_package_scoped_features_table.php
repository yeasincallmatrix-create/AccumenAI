<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('package_scoped_features')) {
            return;
        }

        Schema::create('package_scoped_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_scope_id')
                ->constrained('package_scopes')
                ->cascadeOnDelete();
            $table->string('feature_key', 100);
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->unique(
                ['package_scope_id', 'feature_key'],
                'uq_scoped_feature'
            );
            $table->index('feature_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_scoped_features');
    }
};
