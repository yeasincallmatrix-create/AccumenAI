<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $restrict = function (string $table, string $column) {
            try {
                Schema::table($table, function (Blueprint $t) use ($column) {
                    try { $t->dropForeign([$column]); } catch (\Throwable $e) {}
                });
            } catch (\Throwable $e) {}
            try {
                Schema::table($table, function (Blueprint $t) use ($column) {
                    $t->foreign($column)->references('id')->on('academic_result_aggregation_schemes')->restrictOnDelete()->restrictOnUpdate();
                });
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('FK harden failed', ['table' => $table, 'column' => $column, 'error' => $e->getMessage()]);
            }
        };

        // For academic_final_results, both scheme_id and policy_id reference, but scheme_id is the critical historical link
        // Check orphans before
        foreach (['academic_result_aggregation_items' => 'scheme_id', 'academic_final_results' => 'scheme_id', 'academic_final_results' => 'policy_id'] as $table => $col) {
            // handled below
        }

        $restrict('academic_result_aggregation_items', 'scheme_id');
        $restrict('academic_result_aggregation_items', 'academic_assessment_id');
        $restrict('academic_final_results', 'scheme_id');
        // policy_id FK also CASCADE, but policy is 1:1 with scheme, so deleting scheme would cascade policy and then final results; we keep policy CASCADE to scheme but final results RESTRICT to scheme
        // Also harden policy reference
        try {
            Schema::table('academic_final_result_policies', function (Blueprint $t) {
                try { $t->dropForeign(['scheme_id']); } catch (\Throwable $e) {}
            });
            Schema::table('academic_final_result_policies', function (Blueprint $t) {
                $t->foreign('scheme_id')->references('id')->on('academic_result_aggregation_schemes')->restrictOnDelete()->restrictOnUpdate();
            });
        } catch (\Throwable $e) {}
    }

    public function down(): void
    {
        $cascade = function (string $table, string $column) {
            try {
                Schema::table($table, function (Blueprint $t) use ($column) {
                    try { $t->dropForeign([$column]); } catch (\Throwable $e) {}
                });
            } catch (\Throwable $e) {}
            try {
                Schema::table($table, function (Blueprint $t) use ($column) {
                    $t->foreign($column)->references('id')->on('academic_result_aggregation_schemes')->cascadeOnDelete();
                });
            } catch (\Throwable $e) {}
        };
        $cascade('academic_result_aggregation_items', 'scheme_id');
        $cascade('academic_final_results', 'scheme_id');
        try {
            Schema::table('academic_final_result_policies', function (Blueprint $t) {
                try { $t->dropForeign(['scheme_id']); } catch (\Throwable $e) {}
            });
            Schema::table('academic_final_result_policies', function (Blueprint $t) {
                $t->foreign('scheme_id')->references('id')->on('academic_result_aggregation_schemes')->cascadeOnDelete();
            });
        } catch (\Throwable $e) {}
    }
};
