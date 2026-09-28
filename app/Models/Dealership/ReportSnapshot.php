<?php

namespace App\Models\Dealership;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class ReportSnapshot extends Model
{
    use TenantScoped;

    protected $table = 'dealership_report_snapshots';

    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
        'period_start' => 'date',
        'period_end' => 'date',
        'generated_at' => 'datetime',
    ];
}
