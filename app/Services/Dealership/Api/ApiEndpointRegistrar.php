<?php

namespace App\Services\Dealership\Api;

use Illuminate\Support\Facades\DB;

/**
 * Canonical mobile endpoint registry (discovery for future mobile app).
 */
class ApiEndpointRegistrar
{
    /**
     * @return array{key: string, method: string, uri: string, permission: string|null}[]
     */
    public static function defaults(): array
    {
        return [
            ['key' => 'orders.list', 'method' => 'GET', 'uri' => 'dealership/orders', 'permission' => 'sr_orders.view'],
            ['key' => 'orders.create', 'method' => 'POST', 'uri' => 'dealership/orders', 'permission' => 'sr_orders.manage'],
            ['key' => 'collections.list', 'method' => 'GET', 'uri' => 'dealership/collections', 'permission' => 'sr_collection.view'],
            ['key' => 'collections.create', 'method' => 'POST', 'uri' => 'dealership/collections', 'permission' => 'sr_collection.manage'],
            ['key' => 'customers.list', 'method' => 'GET', 'uri' => 'dealership/customers', 'permission' => 'customers.view'],
            ['key' => 'products.list', 'method' => 'GET', 'uri' => 'dealership/products', 'permission' => 'products.view'],
            ['key' => 'dashboard.kpis', 'method' => 'GET', 'uri' => 'dealership/dashboard', 'permission' => 'dashboard.view'],
        ];
    }

    public function seedDefaults(int $instituteId): void
    {
        foreach (self::defaults() as $def) {
            DB::table('dealership_api_endpoints')->updateOrInsert(
                [
                    'institute_id' => $instituteId,
                    'endpoint_key' => $def['key'],
                    'http_method' => $def['method'],
                ],
                [
                    'uri' => $def['uri'],
                    'description' => null,
                    'required_permission' => $def['permission'],
                    'is_enabled' => true,
                    'version' => config('dealership.api.api_version', 'v1'),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
