<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_usage', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id')->index();
            $table->string('period_type', 10);
            $table->string('period', 20);
            $table->unsignedInteger('requests')->default(0);
            $table->unsignedInteger('tokens')->default(0);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['institute_id', 'period_type', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage');
    }
};
