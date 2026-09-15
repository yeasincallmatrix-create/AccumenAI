<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('home_page_countries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('home_page_id')->constrained()->cascadeOnDelete();
            $table->foreignId('country_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['home_page_id', 'country_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('home_page_countries');
    }
};
