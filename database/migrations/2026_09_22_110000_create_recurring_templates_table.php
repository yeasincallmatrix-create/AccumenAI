<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('recurring_templates')) {
            return;
        }

        Schema::create('recurring_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('template_number', 50);
            $table->string('name', 200);

            $table->string('transaction_type', 30);
            // journal_entry, invoice, vendor_bill, expense, payment

            $table->string('frequency', 20);
            // daily, weekly, biweekly, monthly, quarterly, semiannual, annual, custom
            $table->integer('interval_count')->default(1);
            $table->string('custom_cron', 100)->nullable();

            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->integer('max_occurrences')->nullable();
            $table->integer('occurrences_generated')->default(0);

            $table->timestamp('next_run_at');
            $table->timestamp('last_generated_at')->nullable();

            $table->boolean('auto_post')->default(false);

            $table->string('status', 20)->default('active');
            // active, paused, completed, cancelled, failed

            $table->integer('consecutive_failures')->default(0);
            $table->text('last_error')->nullable();

            $table->json('template_data');

            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('cascade');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('institute_users')->onDelete('set null');

            $table->unique(['institute_id', 'template_number'], 'uniq_recurring_template_number');
            $table->index(['institute_id', 'status']);
            $table->index(['next_run_at', 'status'], 'idx_next_run_status');
            $table->index(['transaction_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_templates');
    }
};
