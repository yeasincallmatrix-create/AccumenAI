<?php

namespace App\Services\Medical;

use App\Models\Medical\Medicine;
use Illuminate\Support\Facades\Http;

/**
 * DGDA drug-registry integration (Bangladesh terminology server).
 *
 * Registry URLs are logical identifiers from the integration spec — the
 * OCL terminology endpoint may be unreachable from some networks, so every
 * remote call is timeout-guarded and fails soft (ok=false) instead of
 * throwing. Local catalog search is the primary path; remote
 * validate/lookup is used by the sync command for single codes.
 */
class DgdaService
{
    public const REGISTRY_SYSTEM = 'https://dgda.gov.bd/drug-registry';

    public const SETTING_KEY = 'medical.dgda.enabled';

    protected string $baseUrl = 'https://tr.ocl.dghs.gov.bd/api/fhir';

    protected int $timeoutSeconds = 10;

    /**
     * Platform-wide kill switch (Super Admin → Configuration Center).
     * Off by default: all DGDA UI, warnings and sync stay dormant.
     */
    public static function enabled(): bool
    {
        try {
            return \App\Models\Setting::get(self::SETTING_KEY, '0') === '1';
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Per-tenant gate: true only when the platform master switch is ON and
     * the institute itself opted in (institute_settings.dgda_enabled).
     * Accepts an Institute model, an institute id, or null (resolves the
     * current tenant; falls back to the platform flag when none exists).
     */
    public static function isEnabledForInstitute(mixed $institute = null): bool
    {
        if (! self::enabled()) {
            return false;
        }

        try {
            $instituteId = $institute instanceof \App\Models\Institute
                ? (int) $institute->getKey()
                : (int) $institute;
            if ($instituteId <= 0) {
                $instituteId = \App\Support\TenantContext::id() ?? \App\Support\Workspace::id();
            }
            if (! $instituteId) {
                return true;
            }

            $row = \App\Models\InstituteSetting::withoutGlobalScopes()
                ->where('institute_id', (int) $instituteId)
                ->first(['dgda_enabled']);

            return $row ? $row->isDgdaEnabled() : false;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Validate a DGDA drug code against the terminology server.
     *
     * @return array{ok: bool, valid?: bool, error?: string}
     */
    public function validateCode(string $code): array
    {
        $code = trim($code);
        if ($code === '') {
            return ['ok' => false, 'error' => 'Empty DGDA code.'];
        }

        try {
            $response = Http::timeout($this->timeoutSeconds)
                ->acceptJson()
                ->get($this->baseUrl.'/CodeSystem/$validate-code', [
                    'system' => self::REGISTRY_SYSTEM,
                    'code' => $code,
                ]);
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'Terminology server unreachable: '.$e->getMessage()];
        }

        if (! $response->successful()) {
            return ['ok' => false, 'error' => 'Terminology server responded '.$response->status().'.'];
        }

        $json = $response->json();
        $result = $json['parameter'] ?? null;
        $valid = null;
        if (is_array($result)) {
            foreach ($result as $param) {
                if (($param['name'] ?? null) === 'result' && array_key_exists('valueBoolean', $param)) {
                    $valid = (bool) $param['valueBoolean'];
                }
            }
        }

        return ['ok' => true, 'valid' => $valid, 'raw' => $json];
    }

    /**
     * Look up drug details for a DGDA code.
     *
     * @return array{ok: bool, display?: string|null, error?: string}
     */
    public function lookupCode(string $code): array
    {
        $code = trim($code);
        if ($code === '') {
            return ['ok' => false, 'error' => 'Empty DGDA code.'];
        }

        try {
            $response = Http::timeout($this->timeoutSeconds)
                ->acceptJson()
                ->get($this->baseUrl.'/CodeSystem/$lookup', [
                    'system' => self::REGISTRY_SYSTEM,
                    'code' => $code,
                ]);
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'Terminology server unreachable: '.$e->getMessage()];
        }

        if (! $response->successful()) {
            return ['ok' => false, 'error' => 'Terminology server responded '.$response->status().'.'];
        }

        $json = $response->json();
        $display = $json['display'] ?? null;

        return ['ok' => true, 'display' => is_string($display) ? $display : null, 'raw' => $json];
    }

    /**
     * Local catalog search (primary path): DGDA-coded rows first, then any
     * name match. Correctly grouped — coded matches always win.
     */
    public function searchLocally(int $instituteId, string $query, int $limit = 50)
    {
        $query = trim($query);

        return Medicine::where('institute_id', $instituteId)
            ->where(function ($q) use ($query) {
                $q->where('brand_name', 'LIKE', "%{$query}%")
                    ->orWhere('generic_name', 'LIKE', "%{$query}%");
            })
            ->orderByRaw('CASE WHEN dgda_code IS NULL THEN 1 ELSE 0 END')
            ->orderBy('brand_name')
            ->limit(max(1, min($limit, 100)))
            ->get(['id', 'brand_name', 'generic_name', 'strength', 'dosage_form', 'dgda_code', 'dgda_status']);
    }

    /**
     * Mark one catalog row from a successful remote validation.
     */
    public function markSynced(Medicine $medicine, ?string $display = null): void
    {
        $medicine->forceFill([
            'dgda_status' => 'synced',
            'dgda_synced_at' => now(),
        ])->save();
    }

    public function markFailed(Medicine $medicine): void
    {
        $medicine->forceFill(['dgda_status' => 'failed'])->save();
    }
}
