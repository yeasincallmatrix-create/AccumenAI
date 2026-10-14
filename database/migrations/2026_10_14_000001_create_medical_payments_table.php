<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Medical billing payments (medical ledger integration).
 *
 * `medical_invoices` only ever carried a single overwritten
 * payment_method/payment_reference pair, so a patient paying twice left no
 * history and nowhere to link a second receipt journal. This table is the
 * per-payment ledger row:
 *
 *   - one row per money-in event (patient payment OR approved TPA claim);
 *   - journal_id links the receipt journal posted for THAT payment
 *     (null while the tenant runs in cash mode pre-payment, or when the
 *     required CoA is missing and posting is skipped fail-soft);
 *   - the invoice's own journal_id stays reserved for the sale journal.
 *
 * Nullable + idempotent (hasTable guard) per this repo's delta-migration
 * convention on top of the data-only schema dump.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('medical_payments')) {
            return;
        }

        Schema::create('medical_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('invoice_id');
            $table->decimal('amount', 15, 2);
            $table->string('payment_method', 50);
            $table->string('payment_reference', 100)->nullable();
            $table->dateTime('paid_at');
            $table->unsignedBigInteger('journal_id')->nullable();
            $table->unsignedBigInteger('received_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['invoice_id', 'paid_at']);
            $table->index(['institute_id', 'paid_at']);

            $table->foreign('institute_id')
                ->references('id')->on('institutes')->onDelete('cascade');
            $table->foreign('branch_id')
                ->references('id')->on('branches')->onDelete('cascade');
            $table->foreign('invoice_id')
                ->references('id')->on('medical_invoices')->onDelete('cascade');
            $table->foreign('journal_id')
                ->references('id')->on('journals')->onDelete('set null');
            // Medical module actor ids come from the `web` (users) guard —
            // mirrors medical_documents.uploaded_by / discharge_summaries.prepared_by.
            $table->foreign('received_by')
                ->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medical_payments');
    }
};
