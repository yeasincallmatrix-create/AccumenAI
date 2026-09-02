<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstituteSetting extends Model
{
    use Concerns\TenantScoped;

    protected $table = 'institute_settings';

    public $timestamps = true;

    protected $guarded = [];

    public const CERTIFICATE_APPROVAL_SUPER_ADMIN = 'super_admin';
    public const CERTIFICATE_APPROVAL_ADMIN = 'admin';

    protected function casts(): array
    {
        return [
            'ai_config' => 'array',
            'notification_settings' => 'array',
            'sales_config' => 'array',
            'purchase_config' => 'array',
            'training_config' => 'array',
        ];
    }

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function getCertificateApprovalModeAttribute(): ?string
    {
        return $this->attributes['certificate_approval_mode'] ?? self::CERTIFICATE_APPROVAL_ADMIN;
    }

    public function isSuperAdminApprovalRequired(): bool
    {
        return $this->certificate_approval_mode !== self::CERTIFICATE_APPROVAL_ADMIN;
    }

    public function isAdminControlled(): bool
    {
        return $this->certificate_approval_mode === self::CERTIFICATE_APPROVAL_ADMIN;
    }
}
