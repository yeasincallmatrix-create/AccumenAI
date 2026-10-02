<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tiered critical list (Phase 3, Gate A) — tables that are in backup scope
     * (institute_id), time-aware eligible (created_at) and business data.
     * Only the ones MISSING deleted_at are listed here; the rest already have it.
     */
    private array $criticalTables = [
        'appointments',
        'attendance',
        'institute_settings',
        'invoices',
        'medical_invoices',
        'payments',
        'prescriptions',
        'purchase_invoice_items',
        'sales_order_lines',
        'transactions',
    ];

    public function up(): void
    {
        foreach ($this->criticalTables as $table) {
            if (!Schema::hasTable($table)) {
                Log::warning("Soft delete: table {$table} not found, skipped");
                continue;
            }

            if (!Schema::hasColumn($table, 'created_at')) {
                Log::warning("Soft delete: {$table} has no created_at, skipped (time-aware required)");
                continue;
            }

            Schema::table($table, function (Blueprint $t) use ($table) {
                if (!Schema::hasColumn($table, 'deleted_at')) {
                    $t->timestamp('deleted_at')->nullable();
                }
                if (!Schema::hasColumn($table, 'deleted_by')) {
                    $t->unsignedBigInteger('deleted_by')->nullable();
                }
                if (!Schema::hasColumn($table, 'deleted_reason')) {
                    $t->string('deleted_reason', 255)->nullable();
                }

                $index = "{$table}_deleted_at_index";
                if (!$this->hasIndex($table, $index)) {
                    $t->index('deleted_at');
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->criticalTables as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) use ($table) {
                $cols = array_values(array_filter(
                    ['deleted_at', 'deleted_by', 'deleted_reason'],
                    fn ($c) => Schema::hasColumn($table, $c)
                ));

                if (!empty($cols)) {
                    $t->dropColumn($cols);
                }
            });
        }
    }

    private function hasIndex(string $table, string $name): bool
    {
        $indexes = \DB::select('SHOW INDEX FROM `' . $table . '` WHERE Key_name = ?', [$name]);

        return !empty($indexes);
    }
};
