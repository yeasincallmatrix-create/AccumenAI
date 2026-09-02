<?php

namespace App\Services\System;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Step 117 — Inventory Data Integrity Audit
 */
class InventoryIntegrityAuditService
{
    public function audit(): array
    {
        $issues = [];

        if (Schema::hasTable('inventory_stock_levels')) {
            $neg = DB::table('inventory_stock_levels')->where('quantity', '<', 0)->count();
            if ($neg > 0) $issues[] = "Negative stock: $neg";
        }

        if (Schema::hasTable('inventory_items')) {
            $dupSku = DB::table('inventory_items')->select('sku', DB::raw('COUNT(*) as c'))->whereNotNull('sku')->groupBy('sku')->having('c','>',1)->count();
            if ($dupSku > 0) $issues[] = "Duplicate SKU: $dupSku";
            $dupBarcode = DB::table('inventory_items')->select('barcode', DB::raw('COUNT(*) as c'))->whereNotNull('barcode')->where('barcode','!=','')->groupBy('barcode')->having('c','>',1)->count();
            if ($dupBarcode > 0) $issues[] = "Duplicate barcode: $dupBarcode";
        }

        // Warehouse ownership
        if (Schema::hasTable('inventory_warehouses') && Schema::hasColumn('inventory_warehouses','institute_id')) {
            $orphan = DB::table('inventory_warehouses')->leftJoin('institutes','inventory_warehouses.institute_id','=','institutes.id')->whereNull('institutes.id')->count();
            if ($orphan > 0) $issues[] = "Warehouse orphan institute: $orphan";
        }

        return [
            'healthy' => empty($issues),
            'issues' => $issues,
            'count' => count($issues),
        ];
    }

    public function report(): string
    {
        $res = $this->audit();
        $lines = ["INVENTORY INTEGRITY AUDIT", str_repeat("=", 40)];
        $lines[] = $res['healthy'] ? "Status: PASS" : "Status: FAIL";
        foreach ($res['issues'] as $iss) $lines[] = "  - $iss";
        if (empty($res['issues'])) $lines[] = "  No issues";
        $lines[] = "Recommendation: Review stock adjustments and warehouse ownership";
        return implode("\n", $lines);
    }
}
