<?php

namespace App\Models\LabIntegration;

use App\Models\Concerns\TenantScoped;
use Database\Factories\LabIntegration\LabAnalyzerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LabAnalyzer extends Model
{
    use HasFactory, SoftDeletes, TenantScoped;

    protected static function newFactory()
    {
        return LabAnalyzerFactory::new();
    }

    protected $table = 'lab_analyzers';
    protected $fillable = [
        'institute_id', 'branch_id', 'code', 'name', 'manufacturer', 'model',
        'serial_no', 'instrument_type', 'protocol', 'adapter_key', 'adapter_version',
        'connection_type', 'host', 'port', 'serial_port', 'baud_rate',
        'parity', 'stop_bits', 'data_bits', 'capabilities', 'is_enabled',
        'status', 'last_seen_at', 'last_message_at', 'last_error_at',
        'last_error_message', 'notes', 'config',
    ];
    protected $casts = [
        'capabilities' => 'array',
        'config' => 'array',
        'is_enabled' => 'boolean',
        'last_seen_at' => 'datetime',
        'last_message_at' => 'datetime',
        'last_error_at' => 'datetime',
    ];

    public const INSTRUMENT_TYPES = ['hematology', 'biochemistry', 'immunoassay', 'urinalysis', 'coagulation', 'blood_gas', 'electrolyte', 'other'];
    public const PROTOCOLS = ['astm', 'hl7', 'vendor', 'file', 'csv'];
    public const CONNECTION_TYPES = ['serial', 'tcp', 'usb', 'file'];
    public const STATUSES = ['active', 'inactive', 'maintenance', 'error'];

    public function institute() { return $this->belongsTo(\App\Models\Institute::class); }
    public function branch() { return $this->belongsTo(\App\Models\Branch::class); }
    public function parameterMaps() { return $this->hasMany(LabAnalyzerParameterMap::class, 'analyzer_id'); }
    // NOTE: lab_samples has no analyzer_id column by design (samples are
    // matched to analyzers via lab_messages); no samples() relation here.
    public function messages() { return $this->hasMany(LabMessage::class, 'analyzer_id'); }
    public function worklists() { return $this->hasMany(LabWorklist::class, 'analyzer_id'); }
    public function credential() { return $this->hasOne(LabDeviceCredential::class, 'analyzer_id'); }

    public function scopeActive($q) { return $q->where('is_enabled', true)->where('status', 'active'); }
    public function scopeForInstitute($q, $id) { return $q->where('institute_id', $id); }

    public function supports(string $capability): bool
    {
        return (bool) ($this->capabilities[$capability] ?? false);
    }
}
