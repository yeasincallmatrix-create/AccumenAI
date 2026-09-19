<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('asset_depreciation_entries')) {
            return;
        }

        Schema::create('asset_depreciation_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('asset_id');
            $table->unsignedBigInteger('run_id')->nullable();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('opening_nbv', 19, 4)->default(0);
            $table->decimal('depreciation_amount', 19, 4)->default(0);
            $table->decimal('accumulated_depreciation', 19, 4)->default(0);
            $table->decimal('closing_nbv', 19, 4)->default(0);
            $table->string('method')->nullable();
            $table->decimal('rate', 10, 4)->nullable();
            $table->decimal('units', 19, 4)->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('asset_depreciation_entries', function (Blueprint $table) {
            $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('restrict');
            $table->foreign('asset_id')->references('id')->on('fixed_assets')->onDelete('restrict');
            $table->foreign('run_id')->references('id')->on('asset_depreciation_runs')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_depreciation_entries');
    }
};
