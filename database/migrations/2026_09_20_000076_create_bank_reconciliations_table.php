<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bank_reconciliations')) {
            return;
        }

        Schema::create('bank_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('statement_line_id');
            $table->unsignedBigInteger('journal_id');
            $table->enum('status', ['matched', 'unmatched', 'ignored'])->default('matched');
            $table->unsignedBigInteger('matched_by')->nullable();
            $table->timestamp('matched_at')->nullable();
            $table->timestamps();
        });

        Schema::table('bank_reconciliations', function (Blueprint $table) {
            $table->foreign('statement_line_id')->references('id')->on('bank_statement_lines')->onDelete('restrict');
            $table->foreign('matched_by')->references('id')->on('institute_users')->onDelete('restrict');
            $table->foreign('journal_id')->references('id')->on('journals')->onDelete('restrict');
            $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_reconciliations');
    }
};
