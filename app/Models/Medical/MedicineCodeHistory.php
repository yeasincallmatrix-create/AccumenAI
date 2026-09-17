<?php

namespace App\Models\Medical;

use App\Models\Institute;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class MedicineCodeHistory extends Model
{
    protected $table = 'medicine_code_history';

    protected $fillable = [
        'institute_id', 'medicine_id', 'old_code', 'new_code',
        'reason', 'migrated_at', 'migrated_by',
    ];

    protected $casts = [
        'migrated_at' => 'datetime',
    ];

    public function institute()
    {
        return $this->belongsTo(Institute::class);
    }

    public function medicine()
    {
        return $this->belongsTo(Medicine::class);
    }

    public function migratedBy()
    {
        return $this->belongsTo(User::class, 'migrated_by');
    }
}
