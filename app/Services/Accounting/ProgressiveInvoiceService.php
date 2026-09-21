<?php

namespace App\Services\Accounting;

use App\Models\Accounting\ProgressiveContract;
use App\Models\Currency;
use App\Models\Invoice;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ProgressiveInvoiceService
{
    public function __construct(
        protected InvoiceService $invoiceService,
        protected JournalPostingService $journalService,
        protected AccountingAuditService $audit,
    ) {}

    public function createContract(array $data): ProgressiveContract
    {
        return DB::transaction(function () use ($data) {
            $data['contract_number'] = $data['contract_number']
                ?? $this->generateContractNumber($data['institute_id']);

            $data['remaining_value'] = $data['total_value'];

            $retentionPct = (float) ($data['retention_percentage'] ?? 0);
            $data['retention_amount'] = round(
                (float) $data['total_value'] * $retentionPct / 100,
                2
            );

            $data['status'] = $data['status'] ?? 'active';

            $contract = ProgressiveContract::create($data);

            $this->audit->log($data['institute_id'], [
                'branch_id' => $data['branch_id'] ?? null,
                'actor_type' => 'user',
                'actor_id' => auth()->id() ?? null,
                'action' => 'create',
                'entity_type' => 'progressive_contract',
                'entity_id' => $contract->id,
                'after_payload' => [
                    'contract_number' => $contract->contract_number,
                    'total_value' => $contract->total_value,
                ],
            ]);

            return $contract;
        });
    }

    public function createProgressInvoice(ProgressiveContract $contract, array $data): Invoice
    {
        if (!$contract->isActive()) {
            throw new InvalidArgumentException("Contract is not active.");
        }

        return DB::transaction(function () use ($contract, $data) {
            $method = $data['billing_method'] ?? 'amount';
            $progressValue = (float) ($data['progress_value'] ?? 0);

            $invoiceGross = match ($method) {
                'percentage' => round((float) $contract->total_value * ($progressValue / 100), 2),
                'amount' => $progressValue,
                'milestone' => $progressValue,
                default => throw new InvalidArgumentException("Invalid billing method: {$method}"),
            };

            if ($invoiceGross <= 0) {
                throw new InvalidArgumentException("Invoice amount must be positive.");
            }

            $newCumulative = (float) $contract->total_billed + $invoiceGross;
            if ($newCumulative > (float) $contract->total_value + 0.01) {
                throw new InvalidArgumentException(
                    "Cumulative billing ({$newCumulative}) exceeds contract value ({$contract->total_value})."
                );
            }

            $isFinal = (bool) ($data['is_final'] ?? false);
            $retentionOnThis = 0.0;
            if (!$isFinal && (float) $contract->retention_percentage > 0) {
                $retentionOnThis = round(
                    $invoiceGross * ((float) $contract->retention_percentage / 100),
                    2
                );
            }

            $retentionToRelease = $isFinal ? (float) $contract->retention_amount - (float) $contract->retention_released : 0.0;

            $lineDescription = $data['line_description'] ?? $contract->title;

            $currencyRecord = Currency::where('code', $contract->currency)->first();
            $currencyId = $currencyRecord?->id;

            $invoiceNumberSeq = 'INV-' . now()->format('Ymd') . '-' . strtoupper(Str::random(5));

            $invoice = $this->invoiceService->create(
                (int) $contract->institute_id,
                $contract->branch_id ? (int) $contract->branch_id : null,
                [
                    'party_id' => (int) $contract->party_id,
                    'invoice_type' => 'other',
                    'due_date' => $data['due_date'] ?? now()->addDays(30)->toDateString(),
                    'currency_id' => $currencyId,
                    'tax_group_id' => $contract->tax_group_id,
                    'note' => "Progress Invoice #" . ($contract->invoices()->count() + 1)
                        . " against contract {$contract->contract_number}",
                    'items' => [
                        [
                            'description' => $lineDescription,
                            'amount' => $invoiceGross,
                        ],
                    ],
                ],
                auth()->id() ?? null
            );

            $invoice->forceFill([
                'progressive_contract_id' => $contract->id,
                'is_progressive' => true,
                'is_final_progressive' => $isFinal,
                'milestone_name' => $data['milestone_name'] ?? null,
                'progress_percentage' => $method === 'percentage'
                    ? $progressValue
                    : $contract->percentBilled(),
                'cumulative_billed' => $newCumulative,
                'retention_amount' => $retentionOnThis,
            ])->save();

            $contract->update([
                'total_billed' => $newCumulative,
                'remaining_value' => round((float) $contract->total_value - $newCumulative, 2),
                'retention_released' => (float) $contract->retention_released + $retentionToRelease,
                'progress_percentage' => round($newCumulative / (float) $contract->total_value * 100, 2),
            ]);

            if ($isFinal || $newCumulative >= (float) $contract->total_value - 0.01) {
                $contract->update([
                    'status' => 'completed',
                    'actual_end_date' => now()->toDateString(),
                ]);
            }

            $this->audit->log($contract->institute_id, [
                'branch_id' => $contract->branch_id,
                'actor_type' => 'user',
                'actor_id' => auth()->id() ?? null,
                'action' => 'create',
                'entity_type' => 'progressive_invoice',
                'entity_id' => $invoice->id,
                'after_payload' => [
                    'invoice_number' => $invoice->invoice_number,
                    'amount' => $invoiceGross,
                    'cumulative_billed' => $newCumulative,
                    'is_final' => $isFinal,
                ],
            ]);

            return $invoice->fresh(['items']);
        });
    }

    public function getContractSummary(ProgressiveContract $contract): array
    {
        return [
            'total_value' => (float) $contract->total_value,
            'total_billed' => (float) $contract->total_billed,
            'total_paid' => (float) $contract->total_paid,
            'remaining_value' => $contract->remainingValue(),
            'retention_held' => $contract->retentionHeld(),
            'retention_released' => (float) $contract->retention_released,
            'percent_billed' => $contract->percentBilled(),
            'invoice_count' => $contract->invoices()->count(),
            'last_invoice' => $contract->invoices()->latest()->first(),
        ];
    }

    public function recalculateContract(ProgressiveContract $contract): void
    {
        DB::transaction(function () use ($contract) {
            $invoices = $contract->invoices()->get();

            $totalBilled = 0.0;
            foreach ($invoices as $inv) {
                $totalBilled += (float) $inv->total_amount;
            }

            $contract->update([
                'total_billed' => round($totalBilled, 2),
                'remaining_value' => round((float) $contract->total_value - $totalBilled, 2),
                'progress_percentage' => $contract->total_value > 0
                    ? round($totalBilled / (float) $contract->total_value * 100, 2)
                    : 0,
            ]);
        });
    }

    public function cancelContract(ProgressiveContract $contract, string $reason): void
    {
        if ($contract->invoices()->where('status', '!=', 'cancelled')->exists()) {
            throw new InvalidArgumentException("Cannot cancel a contract with active invoices.");
        }

        $contract->update([
            'status' => 'cancelled',
            'notes' => ($contract->notes ? $contract->notes . "\n\n" : '') . "Cancelled: {$reason}",
        ]);

        $this->audit->log($contract->institute_id, [
            'branch_id' => $contract->branch_id,
            'actor_type' => 'user',
            'actor_id' => auth()->id() ?? null,
            'action' => 'delete',
            'entity_type' => 'progressive_contract',
            'entity_id' => $contract->id,
            'after_payload' => ['reason' => $reason],
        ]);
    }

    protected function generateContractNumber(int $instituteId): string
    {
        $year = date('Y');
        $prefix = 'PC-' . $year . '-';

        $last = ProgressiveContract::where('institute_id', $instituteId)
            ->where('contract_number', 'like', $prefix . '%')
            ->orderByDesc('contract_number')
            ->value('contract_number');

        if ($last) {
            $seq = (int) substr($last, -5) + 1;
        } else {
            $seq = 1;
        }

        return $prefix . str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
    }
}
