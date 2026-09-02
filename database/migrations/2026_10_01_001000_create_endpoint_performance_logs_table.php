<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('endpoint_performance_logs', function (Blueprint $table) {
            $table->id();
            $table->string('route', 255);
            $table->unsignedInteger('request_count')->default(1);
            $table->decimal('average_response_time', 10, 2)->default(0);
            $table->decimal('maximum_response_time', 10, 2)->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->unsignedInteger('http_4xx_count')->default(0);
            $table->unsignedInteger('http_5xx_count')->default(0);
            $table->timestamps();

            $table->index('route');
            $table->index('created_at');
        });

        Schema::create('database_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('type', 50);
            $table->string('message', 500);
            $table->enum('severity', ['warning','critical'])->default('warning');
            $table->json('metadata')->nullable();
            $table->timestamp('alerted_at')->useCurrent();
            $table->timestamps();

            $table->index(['type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('database_alerts');
        Schema::dropIfExists('endpoint_performance_logs');
    }
};
