<?php

namespace App\Models\LabIntegration;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class LabWorklist extends Model
{
    use TenantScoped;

    protected $table = 'lab_worklists';
    protected $fillable = [
        'institute_id', 'analyzer_id', 'sample_id', 'lab_order_id',
        'order_snapshot', 'tests_snapshot', 'status',
        'sent_at', 'acked_at', 'expires_at', 'error_message',
    ];
    protected $casts = [
        'order_snapshot' => 'array',
        'tests_snapshot' => 'array',
        'sent_at' => 'datetime',
        'acked_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public const STATUSES = ['pending', 'sent', 'acked', 'expired', 'failed'];

    public function institute() { return $this->belongsTo(\App\Models\Institute::class); }
    public function analyzer() { return $this->belongsTo(LabAnalyzer::class, 'analyzer_id'); }
    public function sample() { return $this->belongsTo(LabSample::class, 'sample_id'); }
    public function labOrder() { return $this->belongsTo(\App\Models\Medical\LabOrder::class); }
}
