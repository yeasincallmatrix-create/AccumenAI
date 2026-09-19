<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('asset_depreciation_runs')) {
            return;
        }

        Schema::create('asset_depreciation_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status')->default('posted');
            $table->unsignedBigInteger('journal_id')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('asset_depreciation_runs', function (Blueprint $table) {
            $table->foreign('journal_id')->references('id')->on('journals')->onDelete('restrict');
            $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('restrict');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_depreciation_runs');
    }
};
