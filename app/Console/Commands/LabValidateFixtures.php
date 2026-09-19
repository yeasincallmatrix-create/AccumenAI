<?php

namespace App\Console\Commands;

use App\Services\LabIntegration\Parsers\AstmParser;
use App\Services\LabIntegration\Parsers\CsvParser;
use App\Services\LabIntegration\Parsers\Hl7Parser;
use Illuminate\Console\Command;

class LabValidateFixtures extends Command
{
    protected $signature = 'lab:validate-fixtures';
    protected $description = 'Parse all test fixtures and report any parse errors';

    public function handle(AstmParser $astm, Hl7Parser $hl7, CsvParser $csv): int
    {
        $fixtures = [
            ['astm_cbc_5part.txt', $astm],
            ['astm_cbc_3part.txt', $astm],
            ['hl7_cbc_5part.txt', $hl7],
            ['hl7_abnormal_results.txt', $hl7],
            ['hl7_malformed.txt', $hl7],
            ['csv_cbc.csv', $csv],
            ['raw_text_cbc.txt', $csv],
        ];

        foreach ($fixtures as [$file, $parser]) {
            $path = base_path("tests/Fixtures/LabIntegration/{$file}");
            if (! file_exists($path)) {
                $this->error("Missing: {$file}");

                continue;
            }
            $result = $parser->parse(file_get_contents($path));
            $paramCount = count($result['parameters']);
            $errCount = count($result['parse_errors']);
            $this->info("{$file}: {$paramCount} params, {$errCount} errors");
            foreach ($result['parse_errors'] as $err) {
                $this->warn("  - {$err}");
            }
        }

        return 0;
    }
}
