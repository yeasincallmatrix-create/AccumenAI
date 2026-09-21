<?php

namespace Tests\Feature\LabIntegration;

use App\Jobs\ProcessAnalyzerMessage;
use App\Models\Institute;
use App\Models\LabIntegration\LabAnalyzer;
use App\Models\LabIntegration\LabAnalyzerParameterMap;
use App\Models\LabIntegration\LabMessage;
use App\Models\LabIntegration\LabResultParameter;
use App\Models\LabIntegration\LabSample;
use App\Models\Medical\LabOrder;
use App\Models\Medical\LabResult;
use App\Models\Medical\LabTest;
use App\Models\Medical\NumberSequence;
use App\Models\Medical\Patient;
use App\Services\LabIntegration\DeviceAuthService;
use App\Services\LabIntegration\HmacSignature;
use App\Services\Medical\NumberSequenceService;
use App\Support\TenantContext;
use App\Support\Workspace;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Simulator → gateway → API → DB end-to-end.
 * The "simulator" is a fixture payload posted exactly as the Node.js
 * gateway would (signed JSON body); the job runs synchronously to prove
 * the full pipeline without a live queue worker.
 */
class EndToEndIngestTest extends TestCase
{
    use DatabaseTransactions;

    protected Institute $institute;
    protected LabAnalyzer $analyzer;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->institute = Institute::create([
            'name' => 'E2E Hospital',
            'slug' => 'e2e-hosp-'.uniqid(),
            'industry' => 'healthcare',
            'sub_industry' => 'hospital',
            'country' => 'Bangladesh',
            'status' => 'active',
        ]);
        Workspace::set($this->institute->id);
        TenantContext::set($this->institute->id);

        $this->analyzer = LabAnalyzer::factory()->create([
            'institute_id' => $this->institute->id,
            'code' => 'E2E-XN-1',
            'name' => 'E2E Sysmex XN-550',
            'manufacturer' => 'Sysmex',
            'model' => 'XN-550',
            'adapter_key' => 'sysmex_xn',
            'adapter_version' => 'v1',
            'protocol' => 'astm',
            'instrument_type' => 'hematology',
            'is_enabled' => true,
            'status' => 'active',
        ]);

        $this->token = app(DeviceAuthService::class)->issue($this->analyzer);

        // Seed the WBC map the E2E payload needs.
        LabAnalyzerParameterMap::updateOrCreate(
            ['analyzer_id' => $this->analyzer->id, 'vendor_code' => 'WBC'],
            [
                'institute_id' => $this->institute->id,
                'universal_code' => 'WBC',
                'parameter_key' => 'WBC',
                'unit_from' => '10^3/uL',
                'unit_to' => '10^3/uL',
                'conversion_factor' => 1,
                'ref_low' => 4.0,
                'ref_high' => 11.0,
                'ref_range_text' => '4.0-11.0',
                'is_active' => true,
            ]
        );
    }

    protected function postPayload(string $payload): array
    {
        $body = ['payload' => $payload];
        $json = json_encode($body);
        $ts = time();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->token,
            'X-Lab-Timestamp' => (string) $ts,
            'X-Lab-Signature' => HmacSignature::compute($json, $ts, $this->token),
            'Content-Type' => 'application/json',
        ])->postJson('/api/lab-gateway/results', $body);

        $response->assertStatus(202);

        return [$response->json('message_id'), $payload];
    }

    public function test_full_pipeline_astm_payload_to_stored_result(): void
    {
        // 1. Setup: patient + order + sample + lab test for WBC.
        $patient = Patient::create([
            'institute_id' => $this->institute->id,
            'first_name' => 'E2E',
            'last_name' => 'Patient',
            'gender' => 'male',
            'date_of_birth' => '1990-01-01',
            'phone' => '017'.rand(10000000, 99999999),
            'is_patient' => true,
            'mr_number' => app(NumberSequenceService::class)->next(NumberSequence::TYPE_MR, $this->institute->id),
        ]);
        $labTest = LabTest::create([
            'institute_id' => $this->institute->id,
            'code' => 'CBC-'.uniqid(),
            'name' => 'CBC',
            'is_active' => true,
        ]);
        // Point the WBC map at this catalog test.
        LabAnalyzerParameterMap::where('analyzer_id', $this->analyzer->id)
            ->where('vendor_code', 'WBC')
            ->update(['lab_test_id' => $labTest->id]);

        $order = LabOrder::create([
            'institute_id' => $this->institute->id,
            'patient_id' => $patient->id,
            'order_number' => 'LAB-'.uniqid(),
            'order_date' => now()->format('Y-m-d'),
            'status' => 'collected',
        ]);
        LabSample::create([
            'institute_id' => $this->institute->id,
            'accession_number' => 'ACC-E2E-001',
            'patient_id' => $patient->id,
            'lab_order_id' => $order->id,
            'status' => 'collected',
        ]);

        // 2. Simulator payload (accession matches the sample).
        $payload = "H|^&|||SYSMEX^XN-550^00-18|||||||P|E1394-97|20260920103000\n"
            ."P|1||MRN-E2E||E2E^PATIENT||19900101|M\n"
            ."O|1|ACC-E2E-001||^^^CBC+DIFF|R||20260920103000||||||||||||||||||F\n"
            ."R|1|^^^WBC|7.2|10^3/uL|4.0-11.0|N||F||||20260920103000\n"
            .'L|1|N';
        [$messageId] = $this->postPayload($payload);

        // 3. LabMessage created (sync queue in testing may already have
        // processed it — assert identity, status is asserted after the job).
        $this->assertDatabaseHas('lab_messages', ['id' => $messageId, 'analyzer_id' => $this->analyzer->id]);

        // 4. Run the job synchronously (queue driver is sync-independent here).
        $job = new ProcessAnalyzerMessage($messageId, $this->analyzer->id);
        $job->handle(app(\App\Services\LabIntegration\AnalyzerAdapterRegistry::class), app(\App\Services\LabIntegration\LabResultIngestService::class));

        // 5. LabResult + LabResultParameter created.
        $message = LabMessage::find($messageId);
        $this->assertSame('stored', $message->status);
        $this->assertSame($order->id, (int) $message->lab_order_id);

        $labResult = LabResult::where('lab_order_id', $order->id)
            ->where('lab_test_id', $labTest->id)
            ->firstOrFail();
        $this->assertSame($this->analyzer->id, (int) $labResult->analyzer_id);

        $param = LabResultParameter::where('lab_result_id', $labResult->id)
            ->where('parameter_key', 'WBC')
            ->firstOrFail();
        $this->assertEqualsWithDelta(7.2, (float) $param->value_decimal, 0.0001);
        $this->assertSame('N', $param->flag);

        // 6. Re-running the job is a no-op (idempotent at ingest level).
        $job->handle(app(\App\Services\LabIntegration\AnalyzerAdapterRegistry::class), app(\App\Services\LabIntegration\LabResultIngestService::class));
        $this->assertSame(1, LabResultParameter::where('lab_result_id', $labResult->id)->where('parameter_key', 'WBC')->count());
    }

    public function test_unknown_accession_quarantines_message(): void
    {
        $payload = "H|^&|||SYSMEX^XN-550^00-18|||||||P|E1394-97|20260920103000\n"
            ."P|1||MRN-GHOST||GHOST^PATIENT||19900101|M\n"
            ."O|1|ACC-NOPE-999||^^^CBC+DIFF|R||20260920103000||||||||||||||||||F\n"
            ."R|1|^^^WBC|7.2|10^3/uL|4.0-11.0|N||F||||20260920103000\n"
            .'L|1|N';
        [$messageId] = $this->postPayload($payload);

        $job = new ProcessAnalyzerMessage($messageId, $this->analyzer->id);
        $job->handle(app(\App\Services\LabIntegration\AnalyzerAdapterRegistry::class), app(\App\Services\LabIntegration\LabResultIngestService::class));

        $message = LabMessage::find($messageId);
        $this->assertSame('error', $message->status);
        $this->assertSame('UNKNOWN_SAMPLE', $message->error_code);
        // No LabResult created.
        $this->assertSame(0, LabResult::count());
    }
}
