<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('asset_method_changes')) {
            return;
        }

        Schema::create('asset_method_changes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('asset_id');
            $table->string('old_method')->nullable();
            $table->string('new_method');
            $table->unsignedSmallInteger('old_useful_life_months')->nullable();
            $table->unsignedSmallInteger('new_useful_life_months')->nullable();
            $table->decimal('old_residual_value', 19, 4)->nullable();
            $table->decimal('new_residual_value', 19, 4)->nullable();
            $table->string('reason')->nullable();
            $table->string('status')->default('requested');
            $table->date('effective_date')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('asset_method_changes', function (Blueprint $table) {
            $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('restrict');
            $table->foreign('asset_id')->references('id')->on('fixed_assets')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_method_changes');
    }
};
