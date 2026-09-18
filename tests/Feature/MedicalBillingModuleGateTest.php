<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class MedicalBillingModuleGateTest extends TestCase
{
    use DatabaseTransactions;

    public function test_all_billing_routes_require_module_gate(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($r) => str_starts_with($r->getName() ?? '', 'medical.billing.'));

        $this->assertNotEmpty($routes, 'Expected at least one medical.billing.* route.');

        foreach ($routes as $route) {
            $mw = $route->gatherMiddleware();
            $this->assertContains(
                'medical.module:medical.billing',
                $mw,
                "Route {$route->getName()} missing medical.module:medical.billing middleware."
            );
        }
    }
}
