<?php

namespace App\Services\Purchase;

use App\Models\InstituteSetting;
use App\Services\HrAuditService;
use Illuminate\Support\Facades\DB;

class PurchaseSettingsService
{
    public const DEFAULTS = [
        'numbering' => [
            'order' => ['prefix' => 'PO-', 'padding' => 5],
            'quotation' => ['prefix' => 'PQ-', 'padding' => 5],
            'invoice' => ['prefix' => 'PI-', 'padding' => 5],
            'return' => ['prefix' => 'PR-', 'padding' => 5],
            'receipt' => ['prefix' => 'GRN-', 'padding' => 5],
        ],
    ];

    public function __construct(private readonly HrAuditService $audit) {}

    public function get(int $instituteId): array
    {
        $setting = InstituteSetting::withoutGlobalScopes()->where('institute_id', $instituteId)->first();
        $config = $setting?->purchase_config ?? [];

        return array_replace_recursive(self::DEFAULTS, is_array($config) ? $config : []);
    }

    public function update(int $instituteId, array $data, ?int $actorId = null): array
    {
        return DB::transaction(function () use ($instituteId, $data, $actorId) {
            $setting = InstituteSetting::withoutGlobalScopes()->firstOrCreate(['institute_id' => $instituteId], []);
            $old = $setting->purchase_config ?? [];

            $merged = array_replace_recursive(self::DEFAULTS, is_array($old) ? $old : [], $this->normalize($data));

            // Ensure numbering sub-array stays valid
            $merged['numbering'] = array_replace_recursive(self::DEFAULTS['numbering'], $merged['numbering'] ?? []);

            $setting->update(['purchase_config' => $merged]);

            $this->audit->record($instituteId, $actorId, 'purchase_settings_updated', $instituteId, is_array($old) ? $old : null, $merged);

            return $merged;
        });
    }

    private function normalize(array $data): array
    {
        $out = [];
        if (isset($data['numbering']) && is_array($data['numbering'])) {
            $out['numbering'] = [];
            foreach (['order', 'quotation', 'invoice', 'return', 'receipt'] as $type) {
                if (isset($data['numbering'][$type]) && is_array($data['numbering'][$type])) {
                    $out['numbering'][$type] = [];
                    if (isset($data['numbering'][$type]['prefix'])) {
                        $out['numbering'][$type]['prefix'] = (string) $data['numbering'][$type]['prefix'];
                    }
                    if (isset($data['numbering'][$type]['padding'])) {
                        $out['numbering'][$type]['padding'] = (int) $data['numbering'][$type]['padding'];
                    }
                }
            }
        }

        return $out;
    }
}
