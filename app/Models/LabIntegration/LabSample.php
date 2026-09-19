<?php

namespace App\Models\LabIntegration;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LabSample extends Model
{
    use SoftDeletes, TenantScoped;

    protected $table = 'lab_samples';
    protected $fillable = [
        'institute_id', 'branch_id', 'accession_number', 'barcode',
        'patient_id', 'lab_order_id', 'sample_type', 'container_type',
        'collected_at', 'collected_by', 'collection_site', 'status',
        'rejection_reason', 'rejected_at', 'metadata', 'notes',
    ];
    protected $casts = [
        'collected_at' => 'datetime',
        'rejected_at' => 'datetime',
        'metadata' => 'array',
    ];

    public const STATUSES = ['collected', 'received', 'processing', 'completed', 'rejected', 'cancelled'];

    public function institute() { return $this->belongsTo(\App\Models\Institute::class); }
    public function branch() { return $this->belongsTo(\App\Models\Branch::class); }
    public function patient() { return $this->belongsTo(\App\Models\Medical\Patient::class); }
    public function labOrder() { return $this->belongsTo(\App\Models\Medical\LabOrder::class); }
    public function collectedBy() { return $this->belongsTo(\App\Models\User::class, 'collected_by'); }
    public function messages() { return $this->hasMany(LabMessage::class, 'sample_id'); }

    public function scopeForInstitute($q, $id) { return $q->where('institute_id', $id); }
    public function scopeCollected($q) { return $q->where('status', 'collected'); }
    public function scopeReceived($q) { return $q->where('status', 'received'); }
}
