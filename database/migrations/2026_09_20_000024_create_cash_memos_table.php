<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cash_memos')) {
            return;
        }

        Schema::create('cash_memos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->string('memo_number');
            $table->unsignedBigInteger('student_id')->nullable();
            $table->unsignedBigInteger('party_id')->nullable();
            $table->unsignedBigInteger('journal_id')->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('description')->nullable();
            $table->enum('payment_method', ['cash', 'bkash', 'nagad', 'bank', 'other'])->default('cash');
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('offline_origin_id')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::table('cash_memos', function (Blueprint $table) {
            $table->foreign('offline_origin_id')->references('id')->on('offline_sync_queue')->onDelete('restrict');
            $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('restrict');
            $table->foreign('party_id')->references('id')->on('parties')->onDelete('restrict');
            $table->foreign('journal_id')->references('id')->on('journals')->onDelete('restrict');
            $table->foreign('student_id')->references('id')->on('students')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_memos');
    }
};
