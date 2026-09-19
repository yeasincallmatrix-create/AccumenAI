<?php

namespace Tests\Feature\LabIntegration;

use App\Contracts\LabIntegration\AnalyzerAdapterInterface;
use App\Models\Institute;
use App\Models\LabIntegration\LabAnalyzer;
use App\Models\LabIntegration\LabAnalyzerParameterMap;
use App\Models\LabIntegration\LabDeviceCredential;
use App\Models\LabIntegration\LabMessage;
use App\Models\LabIntegration\LabResultParameter;
use App\Models\LabIntegration\LabSample;
use App\Models\LabIntegration\LabWorklist;
use App\Models\Medical\LabOrder;
use App\Models\Medical\LabResult;
use App\Models\Medical\LabTest;
use App\Models\Medical\NumberSequence;
use App\Models\Medical\Patient;
use App\Services\LabIntegration\AnalyzerAdapterRegistry;
use App\Services\Medical\LabService;
use App\Services\Medical\NumberSequenceService;
use App\Support\TenantContext;
use App\Support\Workspace;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class Phase1FoundationTest extends TestCase
{
    use DatabaseTransactions;

    private function institute(string $name): Institute
    {
        return Institute::create([
            'name' => $name,
            'slug' => str()->slug($name.'-'.uniqid()),
            'industry' => 'healthcare',
            'sub_industry' => 'hospital',
            'country' => 'Bangladesh',
            'status' => 'active',
        ]);
    }

    private function useInstitute(Institute $institute): void
    {
        Workspace::set($institute->id);
        TenantContext::set($institute->id);
    }

    private function patient(Institute $institute): Patient
    {
        return Patient::create([
            'institute_id' => $institute->id,
            'first_name' => 'Phase1',
            'last_name' => 'Patient',
            'gender' => 'male',
            'date_of_birth' => '1990-01-01',
            'phone' => '017'.rand(10000000, 99999999),
            'is_patient' => true,
            'mr_number' => app(NumberSequenceService::class)->next(NumberSequence::TYPE_MR, $institute->id),
        ]);
    }

    private function analyzer(Institute $institute, string $code = 'XN550-A'): LabAnalyzer
    {
        return LabAnalyzer::create([
            'institute_id' => $institute->id,
            'code' => $code,
            'name' => 'Sysmex XN-550',
            'manufacturer' => 'Sysmex',
            'model' => 'XN-550',
            'serial_no' => 'SN-'.uniqid(),
            'instrument_type' => 'hematology',
            'protocol' => 'astm',
            'adapter_key' => 'sysmex_xn',
            'adapter_version' => 'v1',
            'connection_type' => 'tcp',
            'host' => '192.168.1.50',
            'port' => 5000,
            'capabilities' => ['result_upload' => true, 'worklist' => false, 'query' => false, 'bidirectional' => false],
            'is_enabled' => true,
            'status' => 'active',
        ]);
    }

    // ---------- Migration sanity ----------

    public function test_all_new_tables_exist(): void
    {
        foreach (['lab_analyzers', 'lab_analyzer_parameter_maps', 'lab_samples', 'lab_messages', 'lab_worklists', 'lab_device_credentials', 'lab_result_parameters'] as $table) {
            $this->assertTrue(Schema::hasTable($table), "missing table {$table}");
        }
    }

    public function test_lab_orders_has_sample_id_column(): void
    {
        $this->assertTrue(Schema::hasColumn('lab_orders', 'sample_id'));
    }

    public function test_lab_orders_has_accession_number_column(): void
    {
        $this->assertTrue(Schema::hasColumn('lab_orders', 'accession_number'));
    }

    public function test_lab_results_has_analyzer_id_column(): void
    {
        $this->assertTrue(Schema::hasColumn('lab_results', 'analyzer_id'));
        $this->assertTrue(Schema::hasColumn('lab_results', 'lab_message_id'));
        $this->assertTrue(Schema::hasColumn('lab_results', 'institute_id'));
    }

    public function test_lab_results_has_unit_and_flag_columns(): void
    {
        $this->assertTrue(Schema::hasColumn('lab_results', 'unit'));
        $this->assertTrue(Schema::hasColumn('lab_results', 'flag'));
    }

    // ---------- Critical bug fix ----------

    public function test_lab_tests_code_unique_is_per_institute(): void
    {
        $a = $this->institute('Phase1 Hosp A');
        $b = $this->institute('Phase1 Hosp B');

        $this->useInstitute($a);
        LabTest::create(['institute_id' => $a->id, 'code' => 'CBC-P1', 'name' => 'CBC A', 'is_active' => true]);

        $this->useInstitute($b);
        $testB = LabTest::create(['institute_id' => $b->id, 'code' => 'CBC-P1', 'name' => 'CBC B', 'is_active' => true]);

        $this->assertNotNull($testB->id);
    }

    public function test_lab_tests_code_same_institute_rejected(): void
    {
        $a = $this->institute('Phase1 Hosp C');
        $this->useInstitute($a);
        LabTest::create(['institute_id' => $a->id, 'code' => 'CBC-DUP', 'name' => 'CBC 1', 'is_active' => true]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        LabTest::create(['institute_id' => $a->id, 'code' => 'CBC-DUP', 'name' => 'CBC 2', 'is_active' => true]);
    }

    // ---------- Models ----------

    public function test_lab_analyzer_belongs_to_institute(): void
    {
        $institute = $this->institute('Phase1 Hosp D');
        $this->useInstitute($institute);
        $analyzer = $this->analyzer($institute);

        $this->assertEquals($institute->id, $analyzer->institute->id);
    }

    public function test_lab_analyzer_capabilities_cast_to_array(): void
    {
        $institute = $this->institute('Phase1 Hosp E');
        $this->useInstitute($institute);
        $analyzer = $this->analyzer($institute);

        $this->assertIsArray($analyzer->fresh()->capabilities);
        $this->assertTrue($analyzer->fresh()->capabilities['result_upload']);
    }

    public function test_lab_analyzer_supports_method(): void
    {
        $institute = $this->institute('Phase1 Hosp F');
        $this->useInstitute($institute);
        $analyzer = $this->analyzer($institute);

        $this->assertTrue($analyzer->supports('result_upload'));
        $this->assertFalse($analyzer->supports('worklist'));
        $this->assertFalse($analyzer->supports('nonexistent'));
    }

    public function test_lab_sample_belongs_to_patient(): void
    {
        $institute = $this->institute('Phase1 Hosp G');
        $this->useInstitute($institute);
        $patient = $this->patient($institute);

        $sample = LabSample::create([
            'institute_id' => $institute->id,
            'accession_number' => 'LAB-ACC-'.uniqid(),
            'patient_id' => $patient->id,
            'status' => 'collected',
        ]);

        $this->assertEquals($patient->id, $sample->patient->id);
    }

    public function test_lab_sample_accession_unique_per_institute(): void
    {
        $a = $this->institute('Phase1 Hosp H');
        $b = $this->institute('Phase1 Hosp I');
        $this->useInstitute($a);
        $patientA = $this->patient($a);

        LabSample::create(['institute_id' => $a->id, 'accession_number' => 'ACC-SAME', 'patient_id' => $patientA->id]);

        // Same accession in another institute is allowed.
        $this->useInstitute($b);
        $patientB = $this->patient($b);
        $sampleB = LabSample::create(['institute_id' => $b->id, 'accession_number' => 'ACC-SAME', 'patient_id' => $patientB->id]);
        $this->assertNotNull($sampleB->id);

        // Same accession twice in one institute is rejected.
        $this->useInstitute($a);
        $this->expectException(\Illuminate\Database\QueryException::class);
        LabSample::create(['institute_id' => $a->id, 'accession_number' => 'ACC-SAME', 'patient_id' => $patientA->id]);
    }

    public function test_lab_message_idempotency_hash_unique_per_institute(): void
    {
        $institute = $this->institute('Phase1 Hosp J');
        $this->useInstitute($institute);
        $analyzer = $this->analyzer($institute);

        LabMessage::create([
            'institute_id' => $institute->id,
            'analyzer_id' => $analyzer->id,
            'protocol' => 'astm',
            'idempotency_hash' => hash('sha256', 'payload-1'),
            'raw_payload' => 'payload-1',
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        LabMessage::create([
            'institute_id' => $institute->id,
            'analyzer_id' => $analyzer->id,
            'protocol' => 'astm',
            'idempotency_hash' => hash('sha256', 'payload-1'),
            'raw_payload' => 'payload-1',
        ]);
    }

    public function test_lab_result_parameter_belongs_to_lab_result(): void
    {
        $institute = $this->institute('Phase1 Hosp K');
        $this->useInstitute($institute);
        $patient = $this->patient($institute);
        $test = LabTest::create(['institute_id' => $institute->id, 'code' => 'CBC-'.uniqid(), 'name' => 'CBC', 'is_active' => true]);
        $order = LabOrder::create([
            'institute_id' => $institute->id,
            'patient_id' => $patient->id,
            'order_number' => 'LAB-'.uniqid(),
            'order_date' => now()->format('Y-m-d'),
            'status' => 'collected',
        ]);
        $result = LabResult::create(['institute_id' => $institute->id, 'lab_order_id' => $order->id, 'lab_test_id' => $test->id]);

        $param = LabResultParameter::create([
            'institute_id' => $institute->id,
            'lab_result_id' => $result->id,
            'parameter_key' => 'WBC',
            'parameter_name' => 'White Blood Cell Count',
            'value_decimal' => 7.2,
            'unit' => '10^3/uL',
            'flag' => 'N',
        ]);

        $this->assertEquals($result->id, $param->labResult->id);
        $this->assertEquals('7.2000', $param->fresh()->display_value);
    }

    public function test_lab_device_credential_generate_token(): void
    {
        $token = LabDeviceCredential::generateToken();

        $this->assertArrayHasKey('plain', $token);
        $this->assertArrayHasKey('hash', $token);
        $this->assertArrayHasKey('prefix', $token);
        $this->assertEquals(hash('sha256', $token['plain']), $token['hash']);
        $this->assertEquals(substr($token['plain'], 0, 12), $token['prefix']);
    }

    public function test_lab_device_credential_find_by_token(): void
    {
        $institute = $this->institute('Phase1 Hosp L');
        $this->useInstitute($institute);
        $analyzer = $this->analyzer($institute);
        $token = LabDeviceCredential::generateToken();

        LabDeviceCredential::create([
            'institute_id' => $institute->id,
            'analyzer_id' => $analyzer->id,
            'token_hash' => $token['hash'],
            'token_prefix' => $token['prefix'],
            'name' => 'Gateway PC #1',
        ]);

        $found = LabDeviceCredential::findByToken($token['plain']);
        $this->assertNotNull($found);
        $this->assertEquals($analyzer->id, $found->analyzer_id);
        $this->assertNull(LabDeviceCredential::findByToken('invalid-token-value-xxxxxxxxxxxx'));
    }

    public function test_lab_device_credential_is_active(): void
    {
        $institute = $this->institute('Phase1 Hosp M');
        $this->useInstitute($institute);
        $analyzer = $this->analyzer($institute, 'XN550-M1');
        $analyzer2 = $this->analyzer($institute, 'XN550-M2');

        $active = LabDeviceCredential::create([
            'institute_id' => $institute->id, 'analyzer_id' => $analyzer->id,
            'token_hash' => hash('sha256', 't1'), 'token_prefix' => 't1',
        ]);
        $this->assertTrue($active->isActive());

        $revoked = LabDeviceCredential::create([
            'institute_id' => $institute->id, 'analyzer_id' => $analyzer2->id,
            'token_hash' => hash('sha256', 't2'), 'token_prefix' => 't2',
            'revoked_at' => now(),
        ]);
        $this->assertFalse($revoked->isActive());
    }

    // ---------- Tenant isolation ----------

    public function test_lab_analyzer_scoped_by_tenant(): void
    {
        $a = $this->institute('Phase1 Hosp N');
        $b = $this->institute('Phase1 Hosp O');

        $this->useInstitute($a);
        $analyzer = $this->analyzer($a, 'HIDDEN-1');

        $this->useInstitute($b);
        $this->assertNull(LabAnalyzer::where('code', 'HIDDEN-1')->first());
        $this->assertEquals(0, LabAnalyzer::count());

        $this->useInstitute($a);
        $this->assertNotNull(LabAnalyzer::find($analyzer->id));
    }

    public function test_lab_sample_scoped_by_tenant(): void
    {
        $a = $this->institute('Phase1 Hosp P');
        $b = $this->institute('Phase1 Hosp Q');

        $this->useInstitute($a);
        $patient = $this->patient($a);
        LabSample::create(['institute_id' => $a->id, 'accession_number' => 'ACC-HIDE', 'patient_id' => $patient->id]);

        $this->useInstitute($b);
        $this->assertEquals(0, LabSample::count());
    }

    public function test_lab_message_scoped_by_tenant(): void
    {
        $a = $this->institute('Phase1 Hosp R');
        $b = $this->institute('Phase1 Hosp S');

        $this->useInstitute($a);
        LabMessage::create([
            'institute_id' => $a->id, 'protocol' => 'astm',
            'idempotency_hash' => hash('sha256', uniqid()), 'raw_payload' => 'x',
        ]);

        $this->useInstitute($b);
        $this->assertEquals(0, LabMessage::count());
    }

    public function test_lab_result_parameter_scoped_by_tenant(): void
    {
        $a = $this->institute('Phase1 Hosp T');
        $b = $this->institute('Phase1 Hosp U');

        $this->useInstitute($a);
        $patient = $this->patient($a);
        $test = LabTest::create(['institute_id' => $a->id, 'code' => 'CBC-'.uniqid(), 'name' => 'CBC', 'is_active' => true]);
        $order = LabOrder::create([
            'institute_id' => $a->id, 'patient_id' => $patient->id,
            'order_number' => 'LAB-'.uniqid(), 'order_date' => now()->format('Y-m-d'), 'status' => 'collected',
        ]);
        $result = LabResult::create(['institute_id' => $a->id, 'lab_order_id' => $order->id, 'lab_test_id' => $test->id]);
        LabResultParameter::create(['institute_id' => $a->id, 'lab_result_id' => $result->id, 'parameter_key' => 'WBC', 'value_decimal' => 6.1]);

        $this->useInstitute($b);
        $this->assertEquals(0, LabResultParameter::count());
        $this->assertEquals(0, LabResult::count());
        // LabOrder is intentionally NOT TenantScoped (legacy controller-level
        // enforcement), so assert explicit institute scoping instead.
        $this->assertEquals(0, LabOrder::where('institute_id', $b->id)->count());
        $this->assertEquals(0, LabTest::count());
    }

    // ---------- Adapter registry skeleton ----------

    public function test_analyzer_adapter_registry_registers_and_resolves(): void
    {
        $registry = new AnalyzerAdapterRegistry();
        $adapter = new class implements AnalyzerAdapterInterface
        {
            public function key(): string { return 'stub'; }
            public function version(): string { return 'v1'; }
            public function protocol(): string { return 'astm'; }
            public function capabilities(): array { return ['result_upload' => true]; }
            public function parse(string $rawPayload, LabAnalyzer $analyzer): array { return []; }
            public function buildWorklist(array $orderData, LabAnalyzer $analyzer): ?string { return null; }
            public function matchesVendor(string $handshakePayload): bool { return false; }
        };

        $registry->register($adapter);

        $this->assertSame($adapter, $registry->resolve('stub', 'v1'));
        $this->assertCount(1, $registry->all());
    }

    public function test_registry_returns_null_for_unknown_adapter(): void
    {
        $registry = app(AnalyzerAdapterRegistry::class);

        $this->assertNull($registry->resolve('no_such_vendor', 'v9'));
    }

    public function test_lab_worklist_belongs_to_analyzer(): void
    {
        $institute = $this->institute('Phase1 Hosp V');
        $this->useInstitute($institute);
        $analyzer = $this->analyzer($institute);

        $worklist = LabWorklist::create([
            'institute_id' => $institute->id,
            'analyzer_id' => $analyzer->id,
            'order_snapshot' => ['accession' => 'ACC-1'],
            'status' => 'pending',
        ]);

        $this->assertEquals($analyzer->id, $worklist->analyzer->id);
    }

    public function test_lab_parameter_map_links_analyzer_and_test(): void
    {
        $institute = $this->institute('Phase1 Hosp W');
        $this->useInstitute($institute);
        $analyzer = $this->analyzer($institute);
        $test = LabTest::create(['institute_id' => $institute->id, 'code' => 'CBC-'.uniqid(), 'name' => 'CBC', 'is_active' => true]);

        $map = LabAnalyzerParameterMap::create([
            'institute_id' => $institute->id,
            'analyzer_id' => $analyzer->id,
            'vendor_code' => 'WBC',
            'universal_code' => 'WBC',
            'lab_test_id' => $test->id,
            'parameter_key' => 'WBC',
            'unit_from' => '10^3/uL',
            'unit_to' => '10^3/uL',
        ]);

        $this->assertEquals($analyzer->id, $map->analyzer->id);
        $this->assertEquals($test->id, $map->labTest->id);
    }

    // ---------- Existing flow preserved ----------

    public function test_lab_order_manual_flow_still_works(): void
    {
        $institute = $this->institute('Phase1 Hosp X');
        $this->useInstitute($institute);
        $patient = $this->patient($institute);
        $test = LabTest::create(['institute_id' => $institute->id, 'code' => 'CBC-'.uniqid(), 'name' => 'CBC', 'is_active' => true]);

        $order = app(LabService::class)->createOrder(
            ['patient_id' => $patient->id, 'priority' => 'routine'],
            [['lab_test_id' => $test->id]]
        );

        $this->assertEquals('ordered', $order->status);
        $this->assertCount(1, $order->results);
        $this->assertEquals('pending', $order->results->first()->status);

        app(LabService::class)->collectSample($order->fresh());
        $this->assertEquals('collected', $order->fresh()->status);

        $resultId = $order->results->first()->id;
        app(LabService::class)->enterResults($order->fresh(), [$resultId => ['result_value' => '7.2']]);

        $done = $order->fresh();
        $this->assertEquals('completed', $done->status);
        $this->assertEquals('7.2', $done->results->first()->fresh()->result_value);
    }
}
