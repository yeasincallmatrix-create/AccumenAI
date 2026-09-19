<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fee_heads')) {
            return;
        }

        Schema::create('fee_heads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('name');
            $table->string('code')->nullable();
            $table->enum('type', ['admission', 'course_tuition', 'registration', 'exam', 'certificate', 'other'])->default('other');
            $table->decimal('default_amount', 10, 2)->default(0);
            $table->unsignedBigInteger('income_coa_id')->nullable();
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_recurring')->default(false);
            $table->enum('billing_frequency', ['monthly', 'quarterly', 'annually', 'one_time'])->default('one_time');
            $table->string('sort_order')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::table('fee_heads', function (Blueprint $table) {
            $table->foreign('updated_by')->references('id')->on('institute_users')->onDelete('restrict');
            $table->foreign('created_by')->references('id')->on('institute_users')->onDelete('restrict');
            $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('restrict');
            $table->foreign('income_coa_id')->references('id')->on('chart_of_accounts')->onDelete('restrict');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_heads');
    }
};
