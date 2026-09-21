<?php

namespace App\Services\Accounting;

use App\Models\Accounting\RecurringGeneration;
use App\Models\Accounting\RecurringTemplate;
use App\Models\Currency;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RecurringTransactionService
{
    public function __construct(
        protected InvoiceService $invoiceService,
        protected JournalPostingService $journalService,
        protected AccountingAuditService $audit,
    ) {}

    public function processDueTemplates(int $limit = 100): array
    {
        $templates = RecurringTemplate::where('status', 'active')
            ->where('next_run_at', '<=', now())
            ->orderBy('next_run_at')
            ->limit($limit)
            ->get();

        $processed = 0;
        $succeeded = 0;
        $failed = 0;

        foreach ($templates as $template) {
            $processed++;
            $failuresBefore = $template->consecutive_failures;
            $this->generateForTemplate($template);

            $template->refresh();
            if ($template->consecutive_failures > $failuresBefore) {
                $failed++;
            } else {
                $succeeded++;
            }
        }

        return [
            'processed' => $processed,
            'succeeded' => $succeeded,
            'failed' => $failed,
        ];
    }

    public function generateForTemplate(RecurringTemplate $template): ?object
    {
        if (!$template->canGenerate()) {
            $template->update(['status' => 'completed']);

            return null;
        }

        try {
            return DB::transaction(function () use ($template) {
                $scheduledFor = $template->next_run_at;

                $existing = RecurringGeneration::where('template_id', $template->id)
                    ->whereDate('scheduled_for', $scheduledFor->toDateString())
                    ->where('status', 'success')
                    ->exists();

                if ($existing) {
                    $this->advanceSchedule($template);

                    return null;
                }

                $generated = match ($template->transaction_type) {
                    'journal_entry' => $this->generateJournalEntry($template),
                    'invoice' => $this->generateInvoice($template),
                    'vendor_bill' => $this->generateVendorBill($template),
                    'expense' => $this->generateExpense($template),
                    'payment' => $this->generatePayment($template),
                    default => throw new \RuntimeException("Unsupported type: {$template->transaction_type}"),
                };

                RecurringGeneration::create([
                    'institute_id' => $template->institute_id,
                    'template_id' => $template->id,
                    'scheduled_for' => $scheduledFor->toDateString(),
                    'generated_at' => now(),
                    'generated_type' => $generated ? get_class($generated) : null,
                    'generated_id' => $generated?->id,
                    'status' => 'success',
                ]);

                $template->update([
                    'occurrences_generated' => $template->occurrences_generated + 1,
                    'last_generated_at' => now(),
                    'consecutive_failures' => 0,
                    'last_error' => null,
                ]);

                $this->advanceSchedule($template);

                return $generated;
            });
        } catch (\Throwable $e) {
            $this->handleFailure($template, $e);
            Log::error('Recurring template generation failed', [
                'template_id' => $template->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    protected function advanceSchedule(RecurringTemplate $template): void
    {
        $next = $template->computeNextRun();

        if (!$next || ($template->end_date && $next->isAfter($template->end_date->endOfDay()))) {
            $template->update(['status' => 'completed']);

            return;
        }

        if ($template->max_occurrences && ($template->occurrences_generated + 1) >= $template->max_occurrences) {
            $template->update(['status' => 'completed', 'next_run_at' => $next]);

            return;
        }

        $template->update(['next_run_at' => $next]);
    }

    protected function handleFailure(RecurringTemplate $template, \Throwable $e): void
    {
        $failures = $template->consecutive_failures + 1;

        $template->update([
            'consecutive_failures' => $failures,
            'last_error' => substr($e->getMessage(), 0, 500),
            'status' => $failures >= 5 ? 'failed' : 'active',
        ]);

        RecurringGeneration::create([
            'institute_id' => $template->institute_id,
            'template_id' => $template->id,
            'scheduled_for' => $template->next_run_at?->toDateString(),
            'status' => 'failed',
            'error_message' => substr($e->getMessage(), 0, 500),
        ]);
    }

    protected function generateJournalEntry(RecurringTemplate $template): ?object
    {
        $data = $template->template_data;

        $currencyRecord = Currency::where('code', $data['currency'] ?? 'BDT')->first();
        $currencyId = $currencyRecord?->id ?? 1;

        return $this->journalService->create([
            'institute_id' => $template->institute_id,
            'branch_id' => $template->branch_id,
            'journal_date' => now()->toDateString(),
            'type' => $data['journal_type'] ?? 'journal',
            'currency_id' => $currencyId,
            'narration' => $data['narration'] ?? $template->name,
            'entries' => $data['lines'] ?? [],
            'source' => 'app',
            'source_id' => $template->id,
        ], null, $template->auto_post);
    }

    protected function generateInvoice(RecurringTemplate $template): ?Invoice
    {
        $data = $template->template_data;

        return $this->invoiceService->create(
            $template->institute_id,
            $template->branch_id,
            [
                'party_id' => $data['party_id'] ?? null,
                'invoice_type' => $data['invoice_type'] ?? 'other',
                'due_date' => now()->addDays(30)->toDateString(),
                'currency_id' => $data['currency_id'] ?? null,
                'tax_group_id' => $data['tax_group_id'] ?? null,
                'items' => $data['items'] ?? [],
                'note' => "Auto-generated from recurring template {$template->template_number}",
            ],
            null
        );
    }

    protected function generateVendorBill(RecurringTemplate $template): ?Invoice
    {
        $data = $template->template_data;

        return $this->invoiceService->create(
            $template->institute_id,
            $template->branch_id,
            [
                'party_id' => $data['party_id'] ?? null,
                'invoice_type' => 'other',
                'due_date' => now()->addDays(30)->toDateString(),
                'currency_id' => $data['currency_id'] ?? null,
                'items' => $data['items'] ?? [],
                'note' => "Auto-generated vendor bill from recurring template {$template->template_number}",
            ],
            null
        );
    }

    protected function generateExpense(RecurringTemplate $template): ?object
    {
        return $this->generateJournalEntry($template);
    }

    protected function generatePayment(RecurringTemplate $template): ?object
    {
        return $this->generateJournalEntry($template);
    }

    public function generateNow(RecurringTemplate $template): ?object
    {
        return $this->generateForTemplate($template);
    }

    public function pause(RecurringTemplate $template): void
    {
        $template->update(['status' => 'paused']);
    }

    public function resume(RecurringTemplate $template): void
    {
        if ($template->status === 'paused') {
            $template->update(['status' => 'active']);
        }
    }

    public function cancel(RecurringTemplate $template): void
    {
        $template->update(['status' => 'cancelled']);
    }
}
