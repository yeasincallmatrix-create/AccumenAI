<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dgda_medicines', function (Blueprint $table) {
            $table->id();
            $table->string('country_code', 5)->default('BD');
            $table->string('dgda_code', 100)->unique();
            $table->string('dar_number', 100)->nullable();
            $table->string('concept_id', 100)->nullable();
            $table->string('brand_name', 255);
            $table->string('generic_name', 255)->nullable();
            $table->string('strength', 100)->nullable();
            $table->string('dosage_form', 100)->nullable();
            $table->string('route', 100)->nullable();
            $table->string('manufacturer', 255)->nullable();
            $table->string('pack_size', 50)->nullable();
            $table->string('normalized_name', 255)->nullable()->index();
            $table->string('status', 20)->default('active');
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index('country_code');
            $table->index('dar_number');
            $table->index('brand_name');
            $table->index('generic_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dgda_medicines');
    }
};
