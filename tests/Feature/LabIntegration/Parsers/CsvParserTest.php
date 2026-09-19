<?php

namespace Tests\Feature\LabIntegration\Parsers;

use App\Services\LabIntegration\Parsers\CsvParser;
use Tests\TestCase;

class CsvParserTest extends TestCase
{
    protected CsvParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = app(CsvParser::class);
    }

    private function fixture(string $name): string
    {
        return file_get_contents(base_path("tests/Fixtures/LabIntegration/{$name}"));
    }

    public function test_parses_csv_with_header(): void
    {
        $result = $this->parser->parse($this->fixture('csv_cbc.csv'));

        $this->assertCount(5, $result['parameters']);
        $this->assertSame([], $result['parse_errors']);
        $this->assertSame('csv', $result['raw_metadata']['format']);
    }

    public function test_parses_raw_text_mode(): void
    {
        $result = $this->parser->parse($this->fixture('raw_text_cbc.txt'));

        $this->assertCount(5, $result['parameters']);
        $this->assertSame('raw_text', $result['raw_metadata']['format']);
        $this->assertSame([], $result['parse_errors']);
    }

    public function test_extracts_accession_from_raw_text(): void
    {
        $result = $this->parser->parse($this->fixture('raw_text_cbc.txt'));

        $this->assertSame('ACC-2026-00005', $result['accession_number']);
    }

    public function test_normalizes_flags(): void
    {
        $raw = "test_code,value,unit,flag,ref_range\nWBC,18.5,10^3/uL,HH,4.0-11.0\nHGB,8.2,g/dL,LL,13.0-17.0";
        $result = $this->parser->parse($raw);
        $flags = array_column($result['parameters'], 'flag');

        $this->assertSame(['HH', 'LL'], $flags);
    }

    public function test_handles_empty_and_malformed_rows(): void
    {
        $raw = "test_code,value,unit,flag,ref_range\nWBC,,10^3/uL,N,4.0-11.0\n,7.2,g/dL,N,\nHGB,14.1,g/dL,N,13.0-17.0";
        $result = $this->parser->parse($raw);

        $this->assertCount(1, $result['parameters']);
        $this->assertCount(2, $result['parse_errors']);
    }

    public function test_rejects_csv_with_missing_header_columns(): void
    {
        $result = $this->parser->parse("code,val\nWBC,7.2");

        $this->assertSame([], $result['parameters']);
        $this->assertNotEmpty($result['parse_errors']);
    }

    public function test_handles_mixed_line_endings(): void
    {
        $raw = "test_code,value,unit,flag,ref_range\r\nWBC,7.2,10^3/uL,N,4.0-11.0\rRBC,4.85,10^6/uL,N,4.5-5.5\n";
        $result = $this->parser->parse($raw);

        $this->assertCount(2, $result['parameters']);
    }

    public function test_handles_empty_input(): void
    {
        $result = $this->parser->parse('');

        $this->assertSame([], $result['parameters']);
    }
}
