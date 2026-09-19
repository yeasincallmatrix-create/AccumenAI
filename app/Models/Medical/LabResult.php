<?php

namespace App\Models\Medical;

use App\Models\Concerns\TenantScoped;
use App\Models\LabIntegration\LabAnalyzer;
use App\Models\LabIntegration\LabMessage;
use App\Models\LabIntegration\LabResultParameter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LabResult extends Model
{
    use SoftDeletes, TenantScoped;

    protected $table = 'lab_results';

    protected $fillable = [
        'institute_id',
        'lab_order_id',
        'lab_test_id',
        'analyzer_id',
        'lab_message_id',
        'result_value',
        'unit',
        'flag',
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

    public function parameters()
    {
        return $this->hasMany(LabResultParameter::class, 'lab_result_id');
    }

    public function analyzer()
    {
        return $this->belongsTo(LabAnalyzer::class, 'analyzer_id');
    }

    public function labMessage()
    {
        return $this->belongsTo(LabMessage::class, 'lab_message_id');
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
