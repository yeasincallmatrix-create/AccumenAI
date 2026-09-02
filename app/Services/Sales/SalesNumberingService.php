<?php

namespace App\Services\Sales;

use App\Models\SalesSequence;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesNumberingService
{
    public const TYPES = ['quotation', 'sales_order', 'delivery', 'sales_return', 'credit_note'];

    public function nextNumber(int $instituteId, ?int $branchId, string $documentType): string
    {
        if (! in_array($documentType, self::TYPES, true)) {
            throw ValidationException::withMessages(['document_type' => 'Invalid document type.']);
        }

        return DB::transaction(function () use ($instituteId, $branchId, $documentType) {
            $seq = SalesSequence::where('institute_id', $instituteId)
                ->where('branch_id', $branchId)
                ->where('document_type', $documentType)
                ->lockForUpdate()
                ->first();

            if (! $seq) {
                $settings = app(SalesSettingsService::class)->get($instituteId);
                $cfg = $settings['numbering'][$documentType] ?? SalesSettingsService::DEFAULTS['numbering'][$documentType];
                $seq = SalesSequence::create([
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
        $seq = SalesSequence::where('institute_id', $instituteId)
            ->where('branch_id', $branchId)
            ->where('document_type', $documentType)
            ->first();
        if (! $seq) {
            $settings = app(SalesSettingsService::class)->get($instituteId);
            $cfg = $settings['numbering'][$documentType] ?? SalesSettingsService::DEFAULTS['numbering'][$documentType];

            return ($cfg['prefix'] ?? '').str_pad('1', (int) ($cfg['padding'] ?? 5), '0', STR_PAD_LEFT);
        }

        return $seq->prefix.str_pad((string) $seq->next_number, (int) $seq->padding, '0', STR_PAD_LEFT);
    }

    public function configure(int $instituteId, ?int $branchId, string $documentType, string $prefix, int $padding, ?int $actorId = null): SalesSequence
    {
        if (! in_array($documentType, self::TYPES, true)) {
            throw ValidationException::withMessages(['document_type' => 'Invalid document type.']);
        }

        $seq = SalesSequence::firstOrCreate(
            ['institute_id' => $instituteId, 'branch_id' => $branchId, 'document_type' => $documentType],
            ['prefix' => $prefix, 'padding' => $padding, 'next_number' => 1]
        );
        $seq->update(['prefix' => $prefix, 'padding' => $padding]);

        // Keep sales_config in sync for UI defaults
        $settingsService = app(SalesSettingsService::class);
        $settingsService->update($instituteId, [
            'numbering' => [$documentType => ['prefix' => $prefix, 'padding' => $padding]],
        ], $actorId);

        return $seq->fresh();
    }
}
