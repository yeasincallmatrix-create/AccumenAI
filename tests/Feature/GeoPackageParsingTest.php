<?php

namespace Tests\Feature;

use App\Geo\Providers\LocalPackageProvider;
use Tests\TestCase;

/**
 * Phase 1 geo-import hardening: the .json streamer must yield every element
 * of a top-level array (pretty-printed, minified, or single-object files).
 * Regression test for the silent zero-record import bug.
 */
class GeoPackageParsingTest extends TestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmp = sys_get_temp_dir().'/geo-parse-'.uniqid();
        mkdir($this->tmp);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->tmp.'/*') ?: []);
        rmdir($this->tmp);
        parent::tearDown();
    }

    public function test_pretty_json_array_yields_all_records(): void
    {
        $records = [
            ['level' => 1, 'code' => 'BD-DHAKA', 'name' => 'Dhaka'],
            ['level' => 2, 'code' => 'BD-DHAKA-DHAKA', 'name' => 'Dhaka', 'parent_code' => 'BD-DHAKA'],
            // Tricky strings (quotes, brackets) must survive streaming; extra
            // keys are dropped by normalize() — only the contract fields remain.
            ['level' => 3, 'code' => 'BD.U1', 'name' => 'Savar "Model" Town', 'parent_code' => 'BD-DHAKA-DHAKA', 'postal_code' => '1340', 'tags' => ['a', 'b}c', 'd{e']],
        ];
        $path = $this->tmp.'/pretty.json';
        file_put_contents($path, json_encode($records, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $yielded = iterator_to_array((new LocalPackageProvider($path))->records());

        $this->assertCount(3, $yielded);
        $this->assertSame('Savar "Model" Town', $yielded[2]['name']);
        $this->assertSame('1340', $yielded[2]['postal_code']);
        $this->assertArrayNotHasKey('tags', $yielded[2]);
    }

    public function test_minified_json_array_yields_all_records(): void
    {
        $records = [
            ['level' => 1, 'code' => 'A', 'name' => 'Alpha'],
            ['level' => 2, 'code' => 'A-B', 'name' => 'Beta', 'parent_code' => 'A'],
        ];
        $path = $this->tmp.'/min.json';
        file_put_contents($path, json_encode($records));

        $this->assertCount(2, iterator_to_array((new LocalPackageProvider($path))->records()));
    }

    public function test_single_object_json_yields_one_record(): void
    {
        $path = $this->tmp.'/single.json';
        file_put_contents($path, json_encode(['level' => 1, 'code' => 'X', 'name' => 'Solo']));

        $yielded = iterator_to_array((new LocalPackageProvider($path))->records());

        $this->assertCount(1, $yielded);
        $this->assertSame('Solo', $yielded[0]['name']);
    }

    public function test_legacy_flat_shape_yields_zero_importable_records(): void
    {
        // Documents the contract: {level_1,level_2,level_3} rows carry no
        // `level`/`name` and are therefore (correctly) skipped — use the
        // converter endpoint for this shape instead of uploading directly.
        $path = $this->tmp.'/legacy.json';
        file_put_contents($path, json_encode([
            ['level_1' => 'Dhaka', 'level_2' => 'Dhaka', 'level_3' => 'Savar', 'code' => 'BD.U1', 'postal_code' => '1340'],
        ]));

        $this->assertCount(0, iterator_to_array((new LocalPackageProvider($path))->records()));
    }
}
