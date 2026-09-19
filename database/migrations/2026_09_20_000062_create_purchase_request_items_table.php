<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('purchase_request_items')) {
            return;
        }

        Schema::create('purchase_request_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('purchase_request_id');
            $table->unsignedBigInteger('inventory_item_id')->nullable();
            $table->string('description');
            $table->decimal('quantity', 19, 4);
            $table->string('unit')->nullable();
            $table->decimal('estimated_unit_price', 19, 4)->default(0);
            $table->decimal('line_total', 19, 4)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('purchase_request_items', function (Blueprint $table) {
            $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('restrict');
            $table->foreign('purchase_request_id')->references('id')->on('purchase_requests')->onDelete('restrict');
            $table->foreign('inventory_item_id')->references('id')->on('inventory_items')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_request_items');
    }
};
