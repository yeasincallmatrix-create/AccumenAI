<?php

namespace App\Models\LabIntegration;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class LabAnalyzerParameterMap extends Model
{
    use TenantScoped;

    protected $table = 'lab_analyzer_parameter_maps';
    protected $fillable = [
        'institute_id', 'analyzer_id', 'vendor_code', 'vendor_name',
        'universal_code', 'lab_test_id', 'parameter_key',
        'unit_from', 'unit_to', 'conversion_factor',
        'ref_low', 'ref_high', 'ref_range_text',
        'is_active', 'sort_order', 'notes',
    ];
    protected $casts = [
        'conversion_factor' => 'decimal:8',
        'ref_low' => 'decimal:4',
        'ref_high' => 'decimal:4',
        'is_active' => 'boolean',
    ];

    public function institute() { return $this->belongsTo(\App\Models\Institute::class); }
    public function analyzer() { return $this->belongsTo(LabAnalyzer::class, 'analyzer_id'); }
    public function labTest() { return $this->belongsTo(\App\Models\Medical\LabTest::class); }

    public function scopeActive($q) { return $q->where('is_active', true); }
}
