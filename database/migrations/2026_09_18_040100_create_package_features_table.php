<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('package_features', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('package_id');
            $table->string('feature_key', 100);
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->unique(['package_id', 'feature_key']);
            $table->index('package_id');
            $table->index('feature_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_features');
    }
};
