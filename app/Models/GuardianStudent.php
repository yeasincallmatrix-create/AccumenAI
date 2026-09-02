<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Step 47 — Link between a guardian account and a student.
 *
 * A guardian can be linked to many students and a student can have many
 * guardians (father / mother / guardian / other). is_primary marks the
 * default student for the guardian's dashboard. The status flag lets an
 * institute detach a relationship without deleting the account.
 */
class GuardianStudent extends Model
{
    use TenantScoped;

    protected $table = 'student_guardians';

    public $timestamps = true;

    protected $guarded = [];

    protected $casts = [
        'is_primary' => 'boolean',
        'relationship' => 'string',
        'status' => 'string',
    ];

    public const RELATIONSHIP_FATHER = 'father';

    public const RELATIONSHIP_MOTHER = 'mother';

    public const RELATIONSHIP_GUARDIAN = 'guardian';

    public const RELATIONSHIP_OTHER = 'other';

    public const RELATIONSHIPS = [
        self::RELATIONSHIP_FATHER,
        self::RELATIONSHIP_MOTHER,
        self::RELATIONSHIP_GUARDIAN,
        self::RELATIONSHIP_OTHER,
    ];

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_INACTIVE,
    ];

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }
}
