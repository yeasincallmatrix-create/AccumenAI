<?php

namespace App\Services;

use App\Models\InstituteSetting;
use App\Support\TenantContext;

class CertificateApprovalModeService
{
    public function getMode(int $instituteId): string
    {
        $setting = InstituteSetting::withoutGlobalScopes()
            ->where('institute_id', $instituteId)
            ->first();

        return $setting?->certificate_approval_mode ?? InstituteSetting::CERTIFICATE_APPROVAL_ADMIN;
    }

    public function isAdminControlled(int $instituteId): bool
    {
        return $this->getMode($instituteId) === InstituteSetting::CERTIFICATE_APPROVAL_ADMIN;
    }

    public function isSuperAdminRequired(int $instituteId): bool
    {
        return !$this->isAdminControlled($instituteId);
    }

    public function canInstituteUserApprove(int $instituteId): bool
    {
        return $this->isAdminControlled($instituteId);
    }

    public function getCurrentTenantMode(): string
    {
        $instituteId = TenantContext::id();
        if ($instituteId === null) {
            return InstituteSetting::CERTIFICATE_APPROVAL_ADMIN;
        }
        return $this->getMode($instituteId);
    }

    public function setMode(int $instituteId, string $mode): void
    {
        if (!in_array($mode, [InstituteSetting::CERTIFICATE_APPROVAL_ADMIN, InstituteSetting::CERTIFICATE_APPROVAL_SUPER_ADMIN], true)) {
            throw new \InvalidArgumentException("Invalid certificate approval mode: {$mode}");
        }

        $existing = InstituteSetting::withoutGlobalScopes()
            ->where('institute_id', $instituteId)
            ->first();

        if ($existing) {
            $existing->update(['certificate_approval_mode' => $mode]);
        } else {
            InstituteSetting::create([
                'institute_id' => $instituteId,
                'certificate_approval_mode' => $mode,
            ]);
        }
    }
}
