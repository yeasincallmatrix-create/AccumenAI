<?php

namespace App\Models\Medical;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class NursingNote extends Model
{
    use SoftDeletes;

    protected $table = 'nursing_notes';

    protected $fillable = [
        'admission_id',
        'note',
        'recorded_by',
        'recorded_at',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
    ];

    public function admission()
    {
        return $this->belongsTo(Admission::class);
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
