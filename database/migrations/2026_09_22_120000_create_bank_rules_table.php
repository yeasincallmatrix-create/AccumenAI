<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bank_rules')) return;

        Schema::create('bank_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('name', 200);
            $table->integer('priority')->default(100);

            $table->string('match_operator', 10)->default('and');

            $table->string('pattern_field', 30)->default('description');
            $table->string('pattern_type', 20)->default('contains');
            $table->string('pattern_value', 500);

            $table->string('amount_operator', 20)->nullable();
            $table->decimal('amount_min', 19, 4)->nullable();
            $table->decimal('amount_max', 19, 4)->nullable();

            $table->string('direction', 10)->nullable();

            $table->string('action_type', 30);
            $table->unsignedBigInteger('account_id')->nullable();
            $table->unsignedBigInteger('party_id')->nullable();
            $table->string('narration', 500)->nullable();

            $table->boolean('is_active')->default(true);
            $table->integer('times_applied')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('cascade');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('set null');
            $table->foreign('account_id')->references('id')->on('chart_of_accounts')->onDelete('set null');
            $table->foreign('party_id')->references('id')->on('parties')->onDelete('set null');

            $table->index(['institute_id', 'is_active', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_rules');
    }
};
