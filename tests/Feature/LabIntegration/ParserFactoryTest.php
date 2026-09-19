<?php

namespace Tests\Feature\LabIntegration;

use App\Services\LabIntegration\ParserFactory;
use App\Services\LabIntegration\Parsers\AstmParser;
use App\Services\LabIntegration\Parsers\CsvParser;
use App\Services\LabIntegration\Parsers\Hl7Parser;
use Tests\TestCase;

class ParserFactoryTest extends TestCase
{
    protected ParserFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = app(ParserFactory::class);
    }

    public function test_returns_astm_parser_for_astm(): void
    {
        $this->assertInstanceOf(AstmParser::class, $this->factory->for('astm'));
        $this->assertInstanceOf(AstmParser::class, $this->factory->for('ASTM'));
    }

    public function test_returns_hl7_parser_for_hl7(): void
    {
        $this->assertInstanceOf(Hl7Parser::class, $this->factory->for('hl7'));
        $this->assertInstanceOf(Hl7Parser::class, $this->factory->for('hl7v2'));
    }

    public function test_returns_csv_parser_for_csv(): void
    {
        $this->assertInstanceOf(CsvParser::class, $this->factory->for('csv'));
    }

    public function test_returns_csv_parser_for_file(): void
    {
        $this->assertInstanceOf(CsvParser::class, $this->factory->for('file'));
        $this->assertInstanceOf(CsvParser::class, $this->factory->for('raw_text'));
        $this->assertInstanceOf(CsvParser::class, $this->factory->for('vendor'));
    }

    public function test_throws_for_unknown_protocol(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->factory->for('carrier_pigeon');
    }
}
