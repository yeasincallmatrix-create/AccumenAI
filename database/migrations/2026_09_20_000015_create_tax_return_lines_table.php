<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tax_return_lines')) {
            return;
        }

        Schema::create('tax_return_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('tax_return_id');
            $table->unsignedBigInteger('tax_rate_id')->nullable();
            $table->string('description');
            $table->decimal('total_sales', 19, 4)->default(0);
            $table->decimal('total_purchases', 19, 4)->default(0);
            $table->decimal('tax_collected', 19, 4)->default(0);
            $table->decimal('tax_paid', 19, 4)->default(0);
            $table->decimal('net_tax', 19, 4)->default(0);
            $table->timestamps();
        });

        Schema::table('tax_return_lines', function (Blueprint $table) {
            $table->foreign('tax_rate_id')->references('id')->on('tax_rates')->onDelete('restrict');
            $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('restrict');
            $table->foreign('tax_return_id')->references('id')->on('tax_return_periods')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_return_lines');
    }
};
