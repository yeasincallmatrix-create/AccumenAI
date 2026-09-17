<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    public function up(): void
    {
        // Backfill industry_id from industry string
        $industries = DB::table('industries')->get()->keyBy('slug');

        $institutesToBackfill = DB::table('institutes')
            ->whereNotNull('industry')
            ->whereNull('industry_id')
            ->orderBy('id')
            ->get();

        foreach ($institutesToBackfill as $inst) {
            $industry = $industries->get($inst->industry);
            if ($industry) {
                DB::table('institutes')
                    ->where('id', $inst->id)
                    ->update(['industry_id' => $industry->id]);
            }
        }

        // Backfill sub_industry_id with deterministic resolution
        $institutes = DB::table('institutes')
            ->whereNotNull('sub_industry')
            ->whereNull('sub_industry_id')
            ->get();

        foreach ($institutes as $inst) {
            $candidates = DB::table('sub_industries')
                ->where('slug', $inst->sub_industry)
                ->when($inst->industry_id, fn ($q) => $q->where('industry_id', $inst->industry_id))
                ->get();

            if ($candidates->isEmpty()) {
                Log::warning('Backfill: no sub_industry match', [
                    'institute_id' => $inst->id,
                    'sub_industry_slug' => $inst->sub_industry,
                    'industry_slug' => $inst->industry,
                ]);
                continue;
            }

            // Priority 1: Exact country-specific match
            if ($inst->country_id) {
                $exact = $candidates->firstWhere('country_id', $inst->country_id);
                if ($exact) {
                    DB::table('institutes')->where('id', $inst->id)
                        ->update(['sub_industry_id' => $exact->id]);
                    continue;
                }
            }

            // Priority 2: Global match (country_id IS NULL)
            $global = $candidates->firstWhere('country_id', null);
            if ($global) {
                DB::table('institutes')->where('id', $inst->id)
                    ->update(['sub_industry_id' => $global->id]);
                continue;
            }

            // Priority 3: Ambiguous — take first and log
            $first = $candidates->first();
            DB::table('institutes')->where('id', $inst->id)
                ->update(['sub_industry_id' => $first->id]);
            Log::warning('Backfill: ambiguous sub_industry match', [
                'institute_id' => $inst->id,
                'sub_industry_slug' => $inst->sub_industry,
                'candidates' => $candidates->pluck('id', 'country_id')->toArray(),
                'assigned' => $first->id,
            ]);
        }

        // Add FK for country_id if not already present
        $hasFk = DB::select("SELECT COUNT(*) as cnt FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'institutes' AND CONSTRAINT_NAME = 'fk_institutes_country'");
        if (($hasFk[0]->cnt ?? 0) == 0) {
            DB::statement('ALTER TABLE institutes ADD CONSTRAINT fk_institutes_country FOREIGN KEY (country_id) REFERENCES countries (id) ON DELETE SET NULL');
        }
    }

    public function down(): void
    {
        DB::table('institutes')
            ->whereNotNull('industry_id')
            ->update(['industry_id' => null]);
        DB::table('institutes')
            ->whereNotNull('sub_industry_id')
            ->update(['sub_industry_id' => null]);
    }
};
