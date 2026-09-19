<?php

namespace Tests\Feature\LabIntegration\Parsers;

use App\Services\LabIntegration\Parsers\Hl7Parser;
use Tests\TestCase;

class Hl7ParserTest extends TestCase
{
    protected Hl7Parser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = app(Hl7Parser::class);
    }

    private function fixture(string $name): string
    {
        return file_get_contents(base_path("tests/Fixtures/LabIntegration/{$name}"));
    }

    public function test_parses_5part_cbc(): void
    {
        $result = $this->parser->parse($this->fixture('hl7_cbc_5part.txt'));

        $this->assertCount(10, $result['parameters']);
        $this->assertSame('ACC-2026-00001', $result['accession_number']);
        $this->assertSame('SAMPLE001', $result['sample_barcode']);
        $this->assertSame('MRN-12345', $result['patient_hint']);
        $this->assertSame('MSG00001', $result['message_id']);
        $this->assertSame([], $result['parse_errors']);
    }

    public function test_parses_abnormal_flags(): void
    {
        $result = $this->parser->parse($this->fixture('hl7_abnormal_results.txt'));
        $flags = array_column($result['parameters'], 'flag', 'vendor_code');

        $this->assertSame('HH', $flags['WBC']);
        $this->assertSame('LL', $flags['HGB']);
        $this->assertSame('L', $flags['PLT']);
        $this->assertSame('H', $flags['NEU']);
    }

    public function test_handles_malformed_message(): void
    {
        $result = $this->parser->parse($this->fixture('hl7_malformed.txt'));

        // Only HGB is valid; WBC has empty value, second OBX has empty code.
        $this->assertCount(1, $result['parameters']);
        $this->assertSame('HGB', $result['parameters'][0]['vendor_code']);
        $this->assertCount(2, $result['parse_errors']);
    }

    public function test_handles_missing_obx_values(): void
    {
        $raw = "MSH|^~\\&|App|LAB|HIS|H|20260920103000||ORU^R01|M1|P|2.5\n"
            ."OBR|1|ACC-1|S1|CBC|||20260920103000\n"
            ."OBX|1|NM|WBC^WBC|||10^3/uL|4.0-11.0|N|||F";
        $result = $this->parser->parse($raw);

        $this->assertSame([], $result['parameters']);
        $this->assertNotEmpty($result['parse_errors']);
    }

    public function test_handles_empty_input(): void
    {
        $result = $this->parser->parse('');

        $this->assertSame([], $result['parameters']);
        $this->assertNull($result['accession_number']);
    }

    public function test_handles_lf_only_line_endings(): void
    {
        $lf = $this->fixture('hl7_cbc_5part.txt');
        $cr = str_replace("\n", "\r", $lf);

        $this->assertCount(10, $this->parser->parse($lf)['parameters']);
        $this->assertCount(10, $this->parser->parse($cr)['parameters']);
    }

    public function test_returns_universal_shape(): void
    {
        $result = $this->parser->parse($this->fixture('hl7_cbc_5part.txt'));

        foreach (['accession_number', 'sample_barcode', 'patient_hint', 'message_id', 'measured_at', 'parameters', 'raw_metadata', 'parse_errors'] as $key) {
            $this->assertArrayHasKey($key, $result);
        }
        $this->assertSame(13, $result['raw_metadata']['segment_count']);
    }

    public function test_handles_escape_sequences_in_fields(): void
    {
        $raw = "MSH|^~\\&|App|LAB|HIS|H|20260920103000||ORU^R01|M9|P|2.5\n"
            ."OBR|1|ACC-9|S9|CBC|||20260920103000\n"
            .((string) 'OBX|1|NM|WBC^White\\S\\Cell||7.2|10^3/uL|4.0-11.0|N|||F');
        $result = $this->parser->parse($raw);

        // Parser must not throw; escape handling is best-effort at this layer.
        $this->assertCount(1, $result['parameters']);
        $this->assertSame('WBC', $result['parameters'][0]['vendor_code']);
    }

    public function test_does_not_throw_on_binary_input(): void
    {
        $result = $this->parser->parse("\x00\xff\xfe\x01BINARY");

        $this->assertIsArray($result);
        $this->assertArrayHasKey('parameters', $result);
    }
}
