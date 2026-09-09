<?php

namespace App\Models\Medical;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LabResult extends Model
{
    use SoftDeletes;

    protected $table = 'lab_results';

    protected $fillable = [
        'lab_order_id',
        'lab_test_id',
        'result_value',
        'result_text',
        'normal_range',
        'status',
        'comments',
    ];

    public function labOrder()
    {
        return $this->belongsTo(LabOrder::class, 'lab_order_id');
    }

    public function labTest()
    {
        return $this->belongsTo(LabTest::class, 'lab_test_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function getIsAbnormalAttribute()
    {
        return in_array($this->status, ['abnormal', 'critical']);
    }
}
