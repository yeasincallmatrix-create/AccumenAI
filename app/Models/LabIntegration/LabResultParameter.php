<?php

namespace App\Models\LabIntegration;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class LabResultParameter extends Model
{
    use TenantScoped;

    protected $table = 'lab_result_parameters';
    protected $fillable = [
        'institute_id', 'lab_result_id', 'parameter_key', 'parameter_name',
        'value_decimal', 'value_text', 'unit', 'flag',
        'ref_low', 'ref_high', 'ref_range_text',
        'status', 'analyzer_id', 'lab_message_id',
    ];
    protected $casts = [
        'value_decimal' => 'decimal:4',
        'ref_low' => 'decimal:4',
        'ref_high' => 'decimal:4',
    ];

    public const FLAGS = ['N', 'H', 'L', 'HH', 'LL', '*'];

    public function institute() { return $this->belongsTo(\App\Models\Institute::class); }
    public function labResult() { return $this->belongsTo(\App\Models\Medical\LabResult::class); }
    public function analyzer() { return $this->belongsTo(LabAnalyzer::class, 'analyzer_id'); }
    public function labMessage() { return $this->belongsTo(LabMessage::class, 'lab_message_id'); }

    public function getDisplayValueAttribute(): string
    {
        if ($this->value_decimal !== null) {
            return (string) $this->value_decimal;
        }
        return $this->value_text ?? '';
    }
}
