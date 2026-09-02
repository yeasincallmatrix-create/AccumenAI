<?php

namespace Tests;

use App\Support\BranchContext;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        session(['mawa_lang' => 'en']);

        TenantContext::clear();
        BranchContext::clear();
    }
}
