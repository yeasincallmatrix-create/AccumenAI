<?php

namespace Tests\Feature\LabIntegration\Parsers;

use App\Services\LabIntegration\Parsers\AstmParser;
use Tests\TestCase;

class AstmParserTest extends TestCase
{
    protected AstmParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = app(AstmParser::class);
    }

    private function fixture(string $name): string
    {
        return file_get_contents(base_path("tests/Fixtures/LabIntegration/{$name}"));
    }

    public function test_parses_5part_cbc(): void
    {
        $result = $this->parser->parse($this->fixture('astm_cbc_5part.txt'));

        $this->assertCount(14, $result['parameters']);
        $codes = array_column($result['parameters'], 'vendor_code');
        foreach (['WBC', 'RBC', 'HGB', 'HCT', 'MCV', 'MCH', 'MCHC', 'PLT', 'MPV', 'NEU', 'LYM', 'MON', 'EOS', 'BASO'] as $code) {
            $this->assertContains($code, $codes);
        }
        $this->assertSame([], $result['parse_errors']);
    }

    public function test_parses_3part_cbc(): void
    {
        $result = $this->parser->parse($this->fixture('astm_cbc_3part.txt'));

        $this->assertCount(8, $result['parameters']);
        $codes = array_column($result['parameters'], 'vendor_code');
        $this->assertContains('MID', $codes);
        $this->assertContains('GRAN', $codes);
        $this->assertSame('ACC-2026-00002', $result['accession_number']);
    }

    public function test_extracts_accession_number(): void
    {
        $result = $this->parser->parse($this->fixture('astm_cbc_5part.txt'));

        $this->assertSame('ACC-2026-00001', $result['accession_number']);
        $this->assertSame('ACC-2026-00001', $result['sample_barcode']);
    }

    public function test_extracts_patient_hint(): void
    {
        $result = $this->parser->parse($this->fixture('astm_cbc_5part.txt'));

        $this->assertSame('MRN-12345', $result['patient_hint']);
    }

    public function test_normalizes_flags_H_L_HH_LL(): void
    {
        $raw = "O|1|ACC-1||^^^CBC|R||20260920103000||||||||||||||||||F\n"
            ."R|1|^^^WBC|18.5|10^3/uL|4.0-11.0|HH||F\n"
            ."R|2|^^^HGB|8.2|g/dL|13.0-17.0|LL||F\n"
            ."R|3|^^^PLT|95|10^3/uL|150-450|L||F\n"
            ."R|4|^^^NEU|85|%|40-70|H||F\n"
            ."R|5|^^^WBC2|7.0|10^3/uL|4.0-11.0|A||F\n"
            .'L|1|N';
        $result = $this->parser->parse($raw);
        $flags = array_column($result['parameters'], 'flag');

        $this->assertSame(['HH', 'LL', 'L', 'H', '*'], $flags);
    }

    public function test_parses_reference_range(): void
    {
        $result = $this->parser->parse($this->fixture('astm_cbc_5part.txt'));
        $wbc = collect($result['parameters'])->firstWhere('vendor_code', 'WBC');

        $this->assertSame(4.0, $wbc['ref_low']);
        $this->assertSame(11.0, $wbc['ref_high']);
        $this->assertSame('4.0-11.0', $wbc['ref_range']);
    }

    public function test_handles_missing_reference_range(): void
    {
        $raw = "O|1|ACC-9||^^^CBC|R||20260920103000||||||||||||||||||F\n"
            ."R|1|^^^WBC|7.2|10^3/uL||N||F\n"
            .'L|1|N';
        $result = $this->parser->parse($raw);

        $this->assertCount(1, $result['parameters']);
        $this->assertNull($result['parameters'][0]['ref_low']);
        $this->assertNull($result['parameters'][0]['ref_range']);
    }

    public function test_handles_malformed_records_gracefully(): void
    {
        $raw = "O|1|ACC-9||^^^CBC|R||20260920103000||||||||||||||||||F\n"
            ."R|1||7.2|10^3/uL|4.0-11.0|N||F\n"
            ."R|2|^^^HGB||g/dL|13.0-17.0|N||F\n"
            ."R|3|^^^PLT|250|10^3/uL|150-450|N||F\n"
            .'L|1|N';
        $result = $this->parser->parse($raw);

        $this->assertCount(1, $result['parameters']);
        $this->assertCount(2, $result['parse_errors']);
    }

    public function test_handles_empty_input(): void
    {
        $result = $this->parser->parse('');

        $this->assertSame([], $result['parameters']);
        $this->assertNull($result['accession_number']);
    }

    public function test_handles_lf_only_line_endings(): void
    {
        $crlf = str_replace("\n", "\r\n", $this->fixture('astm_cbc_5part.txt'));
        $lf = $this->fixture('astm_cbc_5part.txt');
        $cr = str_replace("\n", "\r", $lf);

        $this->assertCount(14, $this->parser->parse($crlf)['parameters']);
        $this->assertCount(14, $this->parser->parse($lf)['parameters']);
        $this->assertCount(14, $this->parser->parse($cr)['parameters']);
    }

    public function test_does_not_throw_on_binary_input(): void
    {
        $result = $this->parser->parse("\x00\x01\x02\xff\xfeBINARY\x00GARBAGE");

        $this->assertIsArray($result);
        $this->assertArrayHasKey('parameters', $result);
        $this->assertArrayHasKey('parse_errors', $result);
    }

    public function test_returns_universal_shape(): void
    {
        $result = $this->parser->parse($this->fixture('astm_cbc_5part.txt'));

        foreach (['accession_number', 'sample_barcode', 'patient_hint', 'message_id', 'measured_at', 'parameters', 'raw_metadata', 'parse_errors'] as $key) {
            $this->assertArrayHasKey($key, $result);
        }
        $this->assertNotNull($result['measured_at']);
        $this->assertSame(18, $result['raw_metadata']['record_count']);
    }
}
