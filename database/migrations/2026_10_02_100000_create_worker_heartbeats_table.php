<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('worker_heartbeats', function (Blueprint $table) {
            $table->id();
            $table->string('worker_id', 100)->unique();
            $table->string('hostname', 150);
            $table->integer('pid');
            $table->string('queue', 100);
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('last_seen_at')->useCurrent();
            $table->unsignedInteger('jobs_processed')->default(0);
            $table->timestamps();

            $table->index('last_seen_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('worker_heartbeats');
    }
};
