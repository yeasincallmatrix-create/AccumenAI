<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('asset_revaluations')) {
            return;
        }

        Schema::create('asset_revaluations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('asset_id');
            $table->date('revaluation_date');
            $table->decimal('previous_carrying_amount', 19, 4)->default(0);
            $table->decimal('new_carrying_amount', 19, 4)->default(0);
            $table->decimal('difference', 19, 4)->default(0);
            $table->string('reason')->nullable();
            $table->string('status')->default('requested');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('journal_id')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('asset_revaluations', function (Blueprint $table) {
            $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('restrict');
            $table->foreign('asset_id')->references('id')->on('fixed_assets')->onDelete('restrict');
            $table->foreign('journal_id')->references('id')->on('journals')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_revaluations');
    }
};
