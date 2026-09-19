<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('asset_cost_components')) {
            return;
        }

        Schema::create('asset_cost_components', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('asset_id');
            $table->string('component_type')->default('purchase');
            $table->decimal('amount', 19, 4)->default(0);
            $table->string('description')->nullable();
            $table->string('reference')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('asset_cost_components', function (Blueprint $table) {
            $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('restrict');
            $table->foreign('asset_id')->references('id')->on('fixed_assets')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_cost_components');
    }
};
