<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Step 46 — Document category / type.
 *
 * Global (institute_id = null) categories are seeded as defaults; institutes
 * may add their own later. entity_types restricts a category to a set of
 * entity slugs (JSON; null = applies to every entity).
 */
class DocumentCategory extends Model
{
    use SoftDeletes;

    protected $table = 'document_categories';

    protected $guarded = [];

    /**
     * Lifecycle stages a document requirement can be attached to. Mirrors the
     * Education lifecycle: admission → enrollment → placement → assessment →
     * result → promotion → completion/withdrawal/transfer → certificate.
     */
    public const STAGE_ADMISSION = 'admission';

    public const STAGE_ENROLLMENT = 'enrollment';

    public const STAGE_PLACEMENT = 'placement';

    public const STAGE_ASSESSMENT = 'assessment';

    public const STAGE_RESULT = 'result';

    public const STAGE_PROMOTION = 'promotion';

    public const STAGE_COMPLETION = 'completion';

    public const STAGE_WITHDRAWAL = 'withdrawal';

    public const STAGE_TRANSFER = 'transfer';

    public const STAGE_CERTIFICATE = 'certificate';

    public const STAGES = [
        self::STAGE_ADMISSION,
        self::STAGE_ENROLLMENT,
        self::STAGE_PLACEMENT,
        self::STAGE_ASSESSMENT,
        self::STAGE_RESULT,
        self::STAGE_PROMOTION,
        self::STAGE_COMPLETION,
        self::STAGE_WITHDRAWAL,
        self::STAGE_TRANSFER,
        self::STAGE_CERTIFICATE,
    ];

    protected function casts(): array
    {
        return [
            'entity_types' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'is_required' => 'boolean',
            'allowed_file_types' => 'array',
            'max_file_size_kb' => 'integer',
            'expiry_applicable' => 'boolean',
            'verification_required' => 'boolean',
        ];
    }

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    /**
     * Whether this category applies to the given entity slug.
     */
    public function appliesTo(?string $entitySlug): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->entity_types === null || $this->entity_types === []) {
            return true;
        }

        return in_array($entitySlug, $this->entity_types, true);
    }
}
