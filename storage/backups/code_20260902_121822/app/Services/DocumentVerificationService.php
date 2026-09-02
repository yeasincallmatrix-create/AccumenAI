<?php

namespace App\Services;

use App\Models\Document;
use Illuminate\Validation\ValidationException;

/**
 * Step 51 — Document verification workflow.
 *
 * Authorized staff review a document and verify or reject it. Rejection
 * requires a reason. Every action is audited via the existing audit system.
 */
class DocumentVerificationService
{
    public function __construct(private readonly DocumentAuditService $audit) {}

    public function verify(Document $document, int $actorId, ?string $notes = null): Document
    {
        if ($document->verification_status === Document::VERIFICATION_VERIFIED) {
            throw ValidationException::withMessages([
                'document' => 'This document is already verified.',
            ]);
        }

        $old = $this->snapshot($document);

        $document->update([
            'verification_status' => Document::VERIFICATION_VERIFIED,
            'verified_by' => $actorId,
            'verified_at' => now(),
            'verification_notes' => $notes,
            'rejection_reason' => null,
        ]);

        $this->audit->record(
            (int) $document->institute_id,
            $actorId,
            'document_verified',
            $document->id,
            $old,
            $this->snapshot($document),
        );

        return $document;
    }

    public function reject(Document $document, int $actorId, string $reason, ?string $notes = null): Document
    {
        if (trim($reason) === '') {
            throw ValidationException::withMessages([
                'reason' => 'A rejection reason is required.',
            ]);
        }

        $old = $this->snapshot($document);

        $document->update([
            'verification_status' => Document::VERIFICATION_REJECTED,
            'verified_by' => $actorId,
            'verified_at' => now(),
            'rejection_reason' => trim($reason),
            'verification_notes' => $notes,
        ]);

        $this->audit->record(
            (int) $document->institute_id,
            $actorId,
            'document_rejected',
            $document->id,
            $old,
            $this->snapshot($document),
        );

        return $document;
    }

    public function requestReplacement(Document $document, int $actorId, string $reason): Document
    {
        return $this->reject($document, $actorId, $reason, 'Replacement requested.');
    }

    private function snapshot(Document $document): array
    {
        return [
            'verification_status' => $document->verification_status,
            'verified_by' => $document->verified_by,
            'verified_at' => $document->verified_at?->toIso8601String(),
            'rejection_reason' => $document->rejection_reason,
            'verification_notes' => $document->verification_notes,
        ];
    }
}
