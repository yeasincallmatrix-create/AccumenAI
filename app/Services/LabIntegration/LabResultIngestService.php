<?php

namespace App\Services\LabIntegration;

use App\Models\LabIntegration\LabAnalyzer;
use App\Models\LabIntegration\LabMessage;
use App\Models\LabIntegration\LabResultParameter;
use App\Models\Medical\LabResult;
use Illuminate\Support\Facades\DB;

class LabResultIngestService
{
    public function __construct(protected SampleMatcher $matcher) {}

    /**
     * Persist a parsed analyzer result.
     * Assumes LabMessage row already created (with status=parsed).
     *
     * Returns:
     * [
     *   'status'    => 'stored'|'quarantined'|'duplicate'|'error',
     *   'message_id'=> int,
     *   'lab_result_ids' => [int, ...],
     *   'reason'    => string|null,
     * ]
     */
    public function ingest(LabAnalyzer $analyzer, LabMessage $message): array
    {
        $parsed = $message->parsed_json ?? [];

        // 1. Match sample
        $match = $this->matcher->match($analyzer, $parsed);
        if (! $match['matched']) {
            $message->update([
                'status' => 'error',
                'error_code' => 'UNKNOWN_SAMPLE',
                'error_message' => $match['reason'],
                'processed_at' => now(),
            ]);

            return [
                'status' => 'quarantined',
                'message_id' => $message->id,
                'lab_result_ids' => [],
                'reason' => $match['reason'],
            ];
        }

        $sample = $match['sample'];
        $order = $match['order'];
        if (! $order) {
            $message->update([
                'status' => 'error',
                'error_code' => 'ORDER_NOT_FOUND',
                'error_message' => 'Sample matched but no order linked.',
                'processed_at' => now(),
            ]);

            return [
                'status' => 'quarantined',
                'message_id' => $message->id,
                'lab_result_ids' => [],
                'reason' => 'order not found',
            ];
        }

        // 2. Store parameters in a transaction
        $resultIds = [];

        DB::transaction(function () use ($analyzer, $message, $sample, $order, $parsed, &$resultIds) {
            // Group parameters by lab_test_id
            $byTest = [];
            foreach ($parsed['parameters'] ?? [] as $param) {
                $labTestId = $param['lab_test_id'] ?? null;
                if (! $labTestId) {
                    // Unmapped parameter — skip but log
                    continue;
                }
                $byTest[$labTestId][] = $param;
            }

            foreach ($byTest as $labTestId => $params) {
                // Upsert LabResult for this test on this order.
                // institute_id is explicit (queue context has no implicit tenant).
                $labResult = LabResult::firstOrCreate(
                    [
                        'institute_id' => $analyzer->institute_id,
                        'lab_order_id' => $order->id,
                        'lab_test_id' => $labTestId,
                    ],
                    [
                        'result_value' => null,
                        'status' => 'pending',
                    ]
                );

                // Store analyzer metadata on the result
                $labResult->update([
                    'analyzer_id' => $analyzer->id,
                    'lab_message_id' => $message->id,
                ]);

                // Create per-parameter rows
                foreach ($params as $param) {
                    LabResultParameter::updateOrCreate(
                        [
                            'lab_result_id' => $labResult->id,
                            'parameter_key' => $param['parameter_key'] ?? $param['universal_code'] ?? $param['vendor_code'],
                        ],
                        [
                            'institute_id' => $analyzer->institute_id,
                            'parameter_name' => $param['vendor_name'] ?? null,
                            'value_decimal' => is_numeric($param['value']) ? $param['value'] : null,
                            'value_text' => is_numeric($param['value']) ? null : (string) $param['value'],
                            'unit' => $param['unit'] ?? null,
                            'flag' => $param['flag'] ?? null,
                            'ref_low' => $param['ref_low'] ?? null,
                            'ref_high' => $param['ref_high'] ?? null,
                            'ref_range_text' => $param['ref_range'] ?? null,
                            'status' => 'pending',
                            'analyzer_id' => $analyzer->id,
                            'lab_message_id' => $message->id,
                        ]
                    );
                }

                $resultIds[] = $labResult->id;
            }

            $message->update([
                'status' => 'stored',
                'sample_id' => $sample?->id,
                'lab_order_id' => $order->id,
                'processed_at' => now(),
            ]);

            $analyzer->update([
                'last_message_at' => now(),
            ]);
        });

        // Phase 6: broadcast scaffold (log driver = no-op until Reverb in Phase 9).
        // Never let broadcasting break the ingest path.
        if (! empty($resultIds)) {
            try {
                event(new \App\Events\LabAnalyzerResultStored($message->fresh()));
            } catch (\Throwable $e) {
                \Log::warning('LabResultIngestService: broadcast failed', ['error' => $e->getMessage()]);
            }
        }

        return [
            'status' => 'stored',
            'message_id' => $message->id,
            'lab_result_ids' => $resultIds,
            'reason' => null,
        ];
    }
}
