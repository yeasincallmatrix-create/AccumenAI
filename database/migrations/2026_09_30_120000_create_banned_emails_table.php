<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banned_emails', function (Blueprint $table) {
            $table->id();
            $table->string('email', 150)->unique();
            $table->string('reason', 500)->nullable();
            $table->unsignedBigInteger('banned_by')->nullable();
            $table->timestamp('banned_at')->useCurrent();
            $table->boolean('is_permanent')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index('email');
            $table->index('is_permanent');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banned_emails');
    }
};
