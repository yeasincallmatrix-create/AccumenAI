<?php

namespace App\Services\Purchase;

use App\Models\PurchaseSequence;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseNumberingService
{
    public const TYPES = ['invoice', 'quotation', 'order', 'return', 'receipt'];

    public function nextNumber(int $instituteId, ?int $branchId, string $documentType): string
    {
        if (! in_array($documentType, self::TYPES, true)) {
            throw ValidationException::withMessages(['document_type' => 'Invalid document type.']);
        }

        return DB::transaction(function () use ($instituteId, $branchId, $documentType) {
            $seq = PurchaseSequence::where('institute_id', $instituteId)
                ->where('branch_id', $branchId)
                ->where('document_type', $documentType)
                ->lockForUpdate()
                ->first();

            if (! $seq) {
                $settings = app(PurchaseSettingsService::class)->get($instituteId);
                $cfg = $settings['numbering'][$documentType] ?? PurchaseSettingsService::DEFAULTS['numbering'][$documentType];
                $seq = PurchaseSequence::create([
                    'institute_id' => $instituteId,
                    'branch_id' => $branchId,
                    'document_type' => $documentType,
                    'prefix' => $cfg['prefix'] ?? '',
                    'padding' => (int) ($cfg['padding'] ?? 5),
                    'next_number' => 1,
                ]);
            }

            $number = $seq->prefix.str_pad((string) $seq->next_number, (int) $seq->padding, '0', STR_PAD_LEFT);
            $seq->increment('next_number');

            return $number;
        });
    }

    public function preview(int $instituteId, ?int $branchId, string $documentType): string
    {
        $seq = PurchaseSequence::where('institute_id', $instituteId)
            ->where('branch_id', $branchId)
            ->where('document_type', $documentType)
            ->first();
        if (! $seq) {
            $settings = app(PurchaseSettingsService::class)->get($instituteId);
            $cfg = $settings['numbering'][$documentType] ?? PurchaseSettingsService::DEFAULTS['numbering'][$documentType];

            return ($cfg['prefix'] ?? '').str_pad('1', (int) ($cfg['padding'] ?? 5), '0', STR_PAD_LEFT);
        }

        return $seq->prefix.str_pad((string) $seq->next_number, (int) $seq->padding, '0', STR_PAD_LEFT);
    }

    public function configure(int $instituteId, ?int $branchId, string $documentType, string $prefix, int $padding, ?int $actorId = null): PurchaseSequence
    {
        if (! in_array($documentType, self::TYPES, true)) {
            throw ValidationException::withMessages(['document_type' => 'Invalid document type.']);
        }

        $seq = PurchaseSequence::firstOrCreate(
            ['institute_id' => $instituteId, 'branch_id' => $branchId, 'document_type' => $documentType],
            ['prefix' => $prefix, 'padding' => $padding, 'next_number' => 1]
        );
        $seq->update(['prefix' => $prefix, 'padding' => $padding]);

        // Keep purchase_config in sync for UI defaults
        $settingsService = app(PurchaseSettingsService::class);
        $settingsService->update($instituteId, [
            'numbering' => [$documentType => ['prefix' => $prefix, 'padding' => $padding]],
        ], $actorId);

        return $seq->fresh();
    }
}
