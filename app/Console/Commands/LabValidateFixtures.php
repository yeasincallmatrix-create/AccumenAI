<?php

namespace App\Console\Commands;

use App\Models\LabIntegration\LabAnalyzer;
use App\Services\LabIntegration\AnalyzerAdapterRegistry;
use App\Services\LabIntegration\Parsers\AstmParser;
use App\Services\LabIntegration\Parsers\CsvParser;
use App\Services\LabIntegration\Parsers\Hl7Parser;
use Illuminate\Console\Command;

class LabValidateFixtures extends Command
{
    protected $signature = 'lab:validate-fixtures';
    protected $description = 'Parse all test fixtures and report any parse errors';

    public function handle(AstmParser $astm, Hl7Parser $hl7, CsvParser $csv, AnalyzerAdapterRegistry $registry): int
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
            $this->report($file, $result);
        }

        // Phase 3: Sysmex XN-550 fixtures via the registered adapter.
        // A transient (unsaved) analyzer is enough: with no parameter maps
        // the adapter still parses, marking parameters unmapped.
        $sysmex = $registry->resolve('sysmex_xn', 'v1');
        if ($sysmex === null) {
            $this->error('Sysmex adapter not registered');

            return 1;
        }
        $probe = new LabAnalyzer(['institute_id' => 0, 'adapter_key' => 'sysmex_xn']);
        foreach ([
            'Sysmex/xn550_astm_cbc_5part.txt',
            'Sysmex/xn550_hl7_cbc_5part.txt',
            'Sysmex/xn550_astm_retic_mode.txt',
            'Sysmex/xn550_astm_with_errors.txt',
            'Sysmex/xn550_hl7_with_blank_params.txt',
        ] as $file) {
            $path = base_path("tests/Fixtures/LabIntegration/{$file}");
            if (! file_exists($path)) {
                $this->error("Missing: {$file}");

                continue;
            }
            $this->report($file, $sysmex->parse(file_get_contents($path), $probe));
        }

        // Phase 8: Mindray BC-5150 fixtures via the registered adapter.
        $mindray = $registry->resolve('mindray_bc', 'v1');
        if ($mindray === null) {
            $this->error('Mindray adapter not registered');

            return 1;
        }
        $mindrayProbe = new LabAnalyzer(['institute_id' => 0, 'adapter_key' => 'mindray_bc']);
        foreach ([
            'Mindray/bc5150_astm_cbc_3part.txt',
            'Mindray/bc5150_hl7_cbc_3part.txt',
            'Mindray/bc5150_astm_with_na_values.txt',
        ] as $file) {
            $path = base_path("tests/Fixtures/LabIntegration/{$file}");
            if (! file_exists($path)) {
                $this->error("Missing: {$file}");

                continue;
            }
            $this->report($file, $mindray->parse(file_get_contents($path), $mindrayProbe));
        }

        return 0;
    }

    protected function report(string $file, array $result): void
    {
        $paramCount = count($result['parameters']);
        $errCount = count($result['parse_errors']);
        $this->info("{$file}: {$paramCount} params, {$errCount} errors");
        foreach ($result['parse_errors'] as $err) {
            $this->warn("  - {$err}");
        }
    }
}
