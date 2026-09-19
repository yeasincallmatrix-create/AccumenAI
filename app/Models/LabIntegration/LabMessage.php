<?php

namespace App\Models\LabIntegration;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class LabMessage extends Model
{
    use TenantScoped;

    protected $table = 'lab_messages';
    protected $fillable = [
        'institute_id', 'analyzer_id', 'direction', 'protocol',
        'adapter_key', 'adapter_version', 'message_id', 'idempotency_hash',
        'raw_payload', 'parsed_json', 'status', 'error_code', 'error_message',
        'attempts', 'last_attempted_at', 'accession_number',
        'sample_id', 'lab_order_id', 'source_ip', 'source_host',
        'received_at', 'processed_at',
        'resolution_status', 'resolution_notes', 'resolved_by', 'resolved_at',
    ];
    protected $casts = [
        'parsed_json' => 'array',
        'received_at' => 'datetime',
        'processed_at' => 'datetime',
        'last_attempted_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public const STATUSES = ['received', 'parsed', 'matched', 'stored', 'error', 'dead', 'duplicate'];

    public function institute() { return $this->belongsTo(\App\Models\Institute::class); }
    public function analyzer() { return $this->belongsTo(LabAnalyzer::class, 'analyzer_id'); }
    public function sample() { return $this->belongsTo(LabSample::class, 'sample_id'); }
    public function labOrder() { return $this->belongsTo(\App\Models\Medical\LabOrder::class); }

    public function scopePending($q) { return $q->whereIn('status', ['received', 'parsed']); }
    public function scopeFailed($q) { return $q->whereIn('status', ['error', 'dead']); }

    public function scopeDeadLetter($q)
    {
        return $q->where('status', 'dead');
    }

    public function scopeUnresolved($q)
    {
        return $q->whereIn('status', ['error', 'dead'])
            ->whereNull('resolution_status');
    }

    public function isDeadLetter(): bool
    {
        return $this->status === 'dead';
    }

    public function isResolved(): bool
    {
        return $this->resolution_status !== null;
    }
}
