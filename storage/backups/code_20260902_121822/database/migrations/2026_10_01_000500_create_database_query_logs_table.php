<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('database_query_logs', function (Blueprint $table) {
            $table->id();
            $table->text('query');
            $table->decimal('execution_time', 10, 2)->default(0);
            $table->string('connection', 50)->default('mysql');
            $table->string('status', 20)->default('success');
            $table->text('error')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('created_at');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('database_query_logs');
    }
};
