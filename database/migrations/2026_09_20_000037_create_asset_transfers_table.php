<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('asset_transfers')) {
            return;
        }

        Schema::create('asset_transfers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('asset_id');
            $table->unsignedBigInteger('from_branch_id')->nullable();
            $table->unsignedBigInteger('to_branch_id')->nullable();
            $table->unsignedBigInteger('from_location_id')->nullable();
            $table->unsignedBigInteger('to_location_id')->nullable();
            $table->string('from_department')->nullable();
            $table->string('to_department')->nullable();
            $table->string('from_custodian')->nullable();
            $table->string('to_custodian')->nullable();
            $table->date('transfer_date');
            $table->string('reason')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('asset_transfers', function (Blueprint $table) {
            $table->foreign('from_location_id')->references('id')->on('asset_locations')->onDelete('restrict');
            $table->foreign('from_branch_id')->references('id')->on('branches')->onDelete('restrict');
            $table->foreign('to_location_id')->references('id')->on('asset_locations')->onDelete('restrict');
            $table->foreign('asset_id')->references('id')->on('fixed_assets')->onDelete('restrict');
            $table->foreign('to_branch_id')->references('id')->on('branches')->onDelete('restrict');
            $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_transfers');
    }
};
