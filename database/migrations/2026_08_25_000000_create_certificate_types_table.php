<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificate_types', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->string('name', 100);
            $table->string('slug', 120);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->timestamps();

            $table->foreign('institute_id')->references('id')->on('institutes')->cascadeOnDelete();
            $table->unique(['institute_id', 'slug']);
            $table->index('institute_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificate_types');
    }
};
