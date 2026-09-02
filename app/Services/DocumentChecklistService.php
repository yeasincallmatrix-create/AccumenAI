<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\Student;
use Illuminate\Support\Collection;

/**
 * Step 51 — Student document requirement checklist.
 *
 * Determines which documents are required for a student at a given lifecycle
 * stage, and computes an overall readiness status. An uploaded document is NOT
 * treated as verified; readiness distinguishes READY / READY WITH EXCEPTIONS /
 * NOT READY.
 */
class DocumentChecklistService
{
    public const READINESS_READY = 'ready';

    public const READINESS_READY_WITH_EXCEPTIONS = 'ready_with_exceptions';

    public const READINESS_NOT_READY = 'not_ready';

    /**
     * Build the checklist for a student at a lifecycle stage.
     *
     * @return array{
     *     requirements: Collection,
     *     summary: array{required:int, submitted:int, verified:int, missing:int, rejected:int, expired:int},
     *     readiness: string
     * }
     */
    public function forStudent(Student $student, ?string $stage = null): array
    {
        $instituteId = (int) $student->institute_id;

        $required = DocumentCategory::query()
            ->where(fn ($q) => $q->whereNull('institute_id')->orWhere('institute_id', $instituteId))
            ->where('is_active', true)
            ->where('is_required', true)
            ->when($stage, fn ($q) => $q->where('lifecycle_stage', $stage))
            ->orderBy('sort_order')
            ->get()
            ->filter(fn (DocumentCategory $c) => $c->appliesTo('student'))
            ->values();

        $documents = Document::query()
            ->where('documentable_type', Student::class)
            ->where('documentable_id', $student->id)
            ->where('status', Document::STATUS_ACTIVE)
            ->with('category')
            ->get();

        $byCategory = $documents->groupBy('category_id');

        $requirements = $required->map(function (DocumentCategory $category) use ($byCategory) {
            $docs = $byCategory->get($category->id, collect());
            $current = $docs->sortByDesc('version')->first();

            $state = 'missing';
            if ($current !== null) {
                $effective = $current->effectiveVerificationStatus();
                $state = match ($effective) {
                    Document::VERIFICATION_VERIFIED => 'verified',
                    Document::VERIFICATION_REJECTED => 'rejected',
                    Document::VERIFICATION_EXPIRED => 'expired',
                    default => 'submitted',
                };
            }

            return [
                'category_id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'lifecycle_stage' => $category->lifecycle_stage,
                'verification_required' => $category->verification_required,
                'state' => $state,
                'document_id' => $current?->id,
                'version' => $current?->version,
            ];
        });

        $summary = [
            'required' => $requirements->count(),
            'submitted' => $requirements->whereIn('state', ['submitted', 'verified', 'rejected', 'expired'])->count(),
            'verified' => $requirements->where('state', 'verified')->count(),
            'missing' => $requirements->where('state', 'missing')->count(),
            'rejected' => $requirements->where('state', 'rejected')->count(),
            'expired' => $requirements->where('state', 'expired')->count(),
        ];

        return [
            'requirements' => $requirements,
            'summary' => $summary,
            'readiness' => $this->readiness($requirements),
        ];
    }

    /**
     * READY: every required doc verified.
     * READY WITH EXCEPTIONS: none missing/rejected/expired but some only
     *   submitted (pending verification) and verification not required.
     * NOT READY: any missing, rejected, or expired.
     */
    private function readiness(Collection $requirements): string
    {
        if ($requirements->isEmpty()) {
            return self::READINESS_READY;
        }

        $blocking = $requirements->filter(function ($req) {
            if (in_array($req['state'], ['missing', 'rejected', 'expired'], true)) {
                return true;
            }

            return $req['state'] === 'submitted' && $req['verification_required'];
        });

        if ($blocking->isNotEmpty()) {
            return self::READINESS_NOT_READY;
        }

        $pending = $requirements->where('state', 'submitted');

        return $pending->isNotEmpty()
            ? self::READINESS_READY_WITH_EXCEPTIONS
            : self::READINESS_READY;
    }
}
