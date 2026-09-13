<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('query_fingerprints', function (Blueprint $table) {
            $table->id();
            $table->string('fingerprint', 64)->unique();
            $table->text('normalized_query');
            $table->unsignedInteger('execution_count')->default(0);
            $table->decimal('total_duration', 12, 2)->default(0);
            $table->decimal('average_duration', 10, 2)->default(0);
            $table->decimal('maximum_duration', 10, 2)->default(0);
            $table->timestamp('first_seen')->nullable();
            $table->timestamp('last_seen')->nullable();
            $table->timestamps();

            $table->index('execution_count');
            $table->index('average_duration');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('query_fingerprints');
    }
};
