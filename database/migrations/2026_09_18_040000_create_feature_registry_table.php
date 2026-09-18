<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feature_registry', function (Blueprint $table) {
            $table->id();
            $table->string('feature_key', 100)->unique();
            $table->string('module_key', 60)->index();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->string('parent_feature_key', 100)->nullable()->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->enum('status', ['active', 'inactive', 'coming_soon'])->default('active');
            $table->timestamps();

            $table->index(['module_key', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_registry');
    }
};
