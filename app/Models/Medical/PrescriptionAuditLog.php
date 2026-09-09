<?php

namespace App\Models\Medical;

use App\Models\Institute;
use Illuminate\Database\Eloquent\Model;

class PrescriptionAuditLog extends Model
{
    protected $fillable = [
        'institute_id',
        'prescription_id',
        'user_id',
        'user_type',
        'actor_name',
        'action',
        'detail',
    ];

    public function institute()
    {
        return $this->belongsTo(Institute::class);
    }

    public function prescription()
    {
        return $this->belongsTo(Prescription::class);
    }

    /**
     * Record a lifecycle event. Actor resolves from the current guards;
     * falls back to system when unauthenticated (console/jobs).
     */
    public static function record(Prescription $prescription, string $action, ?string $detail = null): self
    {
        $staff = auth('institute_user')->user() ?? auth('web')->user() ?? auth()->user();

        $type = 'system';
        $id = null;
        $name = null;
        if ($staff) {
            $id = $staff->getKey();
            $name = $staff->name ?? trim(($staff->first_name ?? '').' '.($staff->last_name ?? '')) ?: null;
            $type = match (true) {
                $staff instanceof \App\Models\PlatformAdmin => 'platform_admin',
                $staff instanceof \App\Models\InstituteUser => 'institute_user',
                default => 'user',
            };
        }

        return static::create([
            'institute_id' => $prescription->institute_id,
            'prescription_id' => $prescription->id,
            'user_id' => $id,
            'user_type' => $type,
            'actor_name' => $name,
            'action' => $action,
            'detail' => $detail ? mb_substr($detail, 0, 255) : null,
        ]);
    }
}
