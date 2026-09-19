<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fee_structure_items')) {
            return;
        }

        Schema::create('fee_structure_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('fee_structure_id');
            $table->unsignedBigInteger('fee_head_id');
            $table->decimal('amount', 10, 2)->default(0);
            $table->boolean('is_optional')->default(false);
            $table->timestamps();
        });

        Schema::table('fee_structure_items', function (Blueprint $table) {
            $table->foreign('fee_structure_id')->references('id')->on('fee_structures')->onDelete('restrict');
            $table->foreign('fee_head_id')->references('id')->on('fee_heads')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_structure_items');
    }
};
