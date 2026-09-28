<?php

namespace App\Models\Dealership;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    use TenantScoped;

    protected $table = 'dealership_attendance';

    protected $guarded = [];

    protected $casts = [
        'attendance_date' => 'date',
        'check_in_at' => 'datetime',
        'check_out_at' => 'datetime',
    ];

    public function salesForce(): BelongsTo
    {
        return $this->belongsTo(SalesForce::class, 'sales_force_id');
    }

    public function beat(): BelongsTo
    {
        return $this->belongsTo(Beat::class, 'beat_id');
    }
}
