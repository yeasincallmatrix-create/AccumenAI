<?php

namespace App\Models\Medical;

use App\Models\Institute;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class MedicineImportBatch extends Model
{
    protected $table = 'medicine_import_batches';

    public const STATUS_PENDING = 'pending_review';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'batch_id', 'institute_id', 'uploaded_by', 'original_filename',
        'total_rows', 'clean_rows', 'conflict_rows', 'error_rows',
        'parsed_data', 'conflicts', 'status', 'confirmed_at',
        'imported_count', 'skipped_count',
    ];

    protected $casts = [
        'parsed_data' => 'array',
        'conflicts' => 'array',
        'confirmed_at' => 'datetime',
        'total_rows' => 'integer',
        'clean_rows' => 'integer',
        'conflict_rows' => 'integer',
        'error_rows' => 'integer',
        'imported_count' => 'integer',
        'skipped_count' => 'integer',
    ];

    public function institute()
    {
        return $this->belongsTo(Institute::class);
    }

    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function scopeForInstitute($q, int $id)
    {
        return $q->where('medicine_import_batches.institute_id', $id);
    }

    public function scopePending($q)
    {
        return $q->where('medicine_import_batches.status', self::STATUS_PENDING);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
