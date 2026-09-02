<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EnvVerificationTest extends TestCase
{
    public function test_test_environment_is_isolated(): void
    {
        $this->assertSame('testing', config('app.env'));
        $this->assertSame('monetix_test', config('database.connections.mysql.database'));
        $this->assertSame('monetix_test', DB::connection()->getDatabaseName());
        $this->assertNotSame('monetix', DB::connection()->getDatabaseName());
        $this->assertSame('array', config('session.driver'));
        $this->assertSame('array', config('cache.default'));
    }

    public function test_test_database_is_reachable_and_migrated(): void
    {
        $ok = DB::select('select 1 as ok');
        $this->assertSame(1, (int) $ok[0]->ok);

        $tables = DB::select('show tables');
        $names = array_map(static fn ($t) => array_values((array) $t)[0], $tables);
        $this->assertContains('institutes', $names, 'monetix_test is not migrated; run php artisan migrate --env=testing');
        $this->assertContains('grade_scales', $names);
        $this->assertContains('platform_admins', $names);
    }
}