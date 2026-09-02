<?php

namespace App\Services\Sales;

use App\Models\InstituteSetting;
use App\Services\HrAuditService;
use Illuminate\Support\Facades\DB;

class SalesSettingsService
{
    public const DEFAULTS = [
        'enabled' => true,
        'quotation_enabled' => true,
        'sales_order_enabled' => true,
        'delivery_enabled' => true,
        'invoice_integration' => true,
        'default_currency' => 'BDT',
        'default_payment_terms' => 'net_15',
        'default_tax_behavior' => 'exclusive',
        'default_discount_behavior' => 'per_line',
        'numbering' => [
            'quotation' => ['prefix' => 'QUO-', 'padding' => 5],
            'sales_order' => ['prefix' => 'SO-', 'padding' => 5],
            'delivery' => ['prefix' => 'DEL-', 'padding' => 5],
            'sales_return' => ['prefix' => 'SR-', 'padding' => 5],
            'credit_note' => ['prefix' => 'CN-', 'padding' => 5],
        ],
    ];

    public function __construct(private readonly HrAuditService $audit) {}

    public function get(int $instituteId): array
    {
        $setting = InstituteSetting::withoutGlobalScopes()->where('institute_id', $instituteId)->first();
        $config = $setting?->sales_config ?? [];

        return array_replace_recursive(self::DEFAULTS, is_array($config) ? $config : []);
    }

    public function update(int $instituteId, array $data, ?int $actorId = null): array
    {
        return DB::transaction(function () use ($instituteId, $data, $actorId) {
            $setting = InstituteSetting::withoutGlobalScopes()->firstOrCreate(['institute_id' => $instituteId], []);
            $old = $setting->sales_config ?? [];

            $merged = array_replace_recursive(self::DEFAULTS, is_array($old) ? $old : [], $this->normalize($data));

            // Ensure numbering sub-array stays valid
            $merged['numbering'] = array_replace_recursive(self::DEFAULTS['numbering'], $merged['numbering'] ?? []);

            $setting->update(['sales_config' => $merged]);

            $this->audit->record($instituteId, $actorId, 'sales_settings_updated', $instituteId, is_array($old) ? $old : null, $merged);

            return $merged;
        });
    }

    public function isEnabled(int $instituteId): bool
    {
        return (bool) $this->get($instituteId)['enabled'];
    }

    private function normalize(array $data): array
    {
        $out = [];
        foreach (['enabled', 'quotation_enabled', 'sales_order_enabled', 'delivery_enabled', 'invoice_integration'] as $k) {
            if (array_key_exists($k, $data)) {
                $out[$k] = (bool) $data[$k];
            }
        }
        foreach (['default_currency', 'default_payment_terms', 'default_tax_behavior', 'default_discount_behavior'] as $k) {
            if (array_key_exists($k, $data)) {
                $out[$k] = (string) $data[$k];
            }
        }
        if (isset($data['numbering']) && is_array($data['numbering'])) {
            $out['numbering'] = [];
            foreach (['quotation', 'sales_order', 'delivery', 'sales_return', 'credit_note'] as $type) {
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
