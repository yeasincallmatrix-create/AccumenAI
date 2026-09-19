<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('asset_impairments')) {
            return;
        }

        Schema::create('asset_impairments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('asset_id');
            $table->date('impairment_date');
            $table->decimal('impairment_amount', 19, 4)->default(0);
            $table->decimal('recoverable_amount', 19, 4)->nullable();
            $table->string('reason')->nullable();
            $table->unsignedBigInteger('journal_id')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('asset_impairments', function (Blueprint $table) {
            $table->foreign('asset_id')->references('id')->on('fixed_assets')->onDelete('restrict');
            $table->foreign('journal_id')->references('id')->on('journals')->onDelete('restrict');
            $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_impairments');
    }
};
