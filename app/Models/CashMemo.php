<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashMemo extends Model
{
    use Concerns\TenantScoped;

    protected $table = 'cash_memos';

    public $timestamps = false;

    protected $guarded = [];

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'party_id');
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class, 'journal_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(InstituteUser::class, 'created_by');
    }

    public function offlineOrigin(): BelongsTo
    {
        return $this->belongsTo(OfflineSyncQueue::class, 'offline_origin_id');
    }
}
