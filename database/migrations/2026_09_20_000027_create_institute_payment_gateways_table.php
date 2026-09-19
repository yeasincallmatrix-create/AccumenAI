<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('institute_payment_gateways')) {
            return;
        }

        Schema::create('institute_payment_gateways', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('gateway_id');
            $table->boolean('is_enabled')->default(true);
            $table->longText('credentials')->nullable();
            $table->longText('settings')->nullable();
            $table->timestamps();
        });

        Schema::table('institute_payment_gateways', function (Blueprint $table) {
            $table->foreign('gateway_id')->references('id')->on('payment_gateways')->onDelete('restrict');
            $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('institute_payment_gateways');
    }
};
