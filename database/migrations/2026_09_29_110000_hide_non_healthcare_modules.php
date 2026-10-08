<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Hide non-healthcare industry modules (real_estate, restaurant,
     * manufacturing, retail — roots and their subtrees) for the healthcare
     * industry: force enabled=false / category='hidden' on every
     * package_industries row mapped to healthcare.
     *
     * Matches config('industry-modules.healthcare.disabled'). Previously
     * these modules were selectable and Medical Enterprise even had ~150
     * of them switched on, which the resolver then granted to hospital
     * tenants. Resolution-neutral for everything else.
     */
    public function up(): void
    {
        if (! Schema::hasTable('package_industry_modules')) {
            return;
        }

        $roots = ['real_estate', 'restaurant', 'manufacturing', 'retail'];
        $hasCategory = Schema::hasColumn('package_industry_modules', 'category');

        DB::transaction(function () use ($roots, $hasCategory) {
            foreach ($roots as $root) {
                $update = ['enabled' => false, 'updated_at' => now()];
                if ($hasCategory) {
                    $update['category'] = 'hidden';
                }

                DB::table('package_industry_modules')
                    ->whereIn('industry_key', ['healthcare', 'medical'])
                    ->where(function ($q) use ($root) {
                        $q->where('module_key', $root)
                            ->orWhere('module_key', 'like', $root.'.%');
                    })
                    ->update($update);
            }
        });
    }

    public function down(): void
    {
        // Previous ON states are unrecoverable — re-enable via the
        // package-industry modules UI instead. No-op by design.
    }
};
