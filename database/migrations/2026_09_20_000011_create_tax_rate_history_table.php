<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tax_rate_history')) {
            return;
        }

        Schema::create('tax_rate_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('tax_rate_id');
            $table->decimal('old_rate', 10, 4);
            $table->decimal('new_rate', 10, 4);
            $table->date('changed_at');
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->timestamps();
        });

        Schema::table('tax_rate_history', function (Blueprint $table) {
            $table->foreign('tax_rate_id')->references('id')->on('tax_rates')->onDelete('restrict');
            $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_rate_history');
    }
};
