<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institute_feature_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')
                  ->constrained('institutes')
                  ->cascadeOnDelete();
            $table->string('feature_key', 100);
            $table->boolean('enabled');
            $table->unsignedBigInteger('overridden_by')->nullable();
            $table->string('reason', 255)->nullable();
            $table->timestamps();

            $table->unique(['institute_id', 'feature_key'], 'ifo_institute_feature_unique');
            $table->index('feature_key');

            $table->foreign('overridden_by')
                  ->references('id')
                  ->on('platform_admins')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('institute_feature_overrides');
    }
};
