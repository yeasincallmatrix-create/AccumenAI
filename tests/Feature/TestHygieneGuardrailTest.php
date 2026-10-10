<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class TestHygieneGuardrailTest extends TestCase
{
    use DatabaseTransactions;

    private const ALLOW_LIST = [];

    private const WRITE_REGEX = '/->create\(|->factory\(\)->create|->update\(|->save\(|->delete\(|::create\(/';

    public function test_feature_tests_that_write_to_db_use_a_transaction_trait(): void
    {
        $dir = base_path('tests/Feature');
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );

        $offenders = [];

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $path = $file->getRealPath();
            $relative = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $path);

            if (in_array($relative, self::ALLOW_LIST, true)) {
                continue;
            }

            $content = file_get_contents($path);

            $hasWrite = preg_match(self::WRITE_REGEX, $content);
            $hasTrait = str_contains($content, 'DatabaseTransactions') || str_contains($content, 'RefreshDatabase');

            if ($hasWrite && ! $hasTrait) {
                $offenders[] = $relative;
            }
        }

        sort($offenders);

        $this->assertSame(
            [],
            $offenders,
            "Feature tests with DB writes must use a transaction trait (DatabaseTransactions or RefreshDatabase). Offenders:\n".
            implode("\n", $offenders)
        );
    }
}
