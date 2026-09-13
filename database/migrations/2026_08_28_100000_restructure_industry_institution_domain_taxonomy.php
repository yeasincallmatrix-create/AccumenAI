<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    public function up(): void
    {
        $autoMap = [
            [['education', 'institution'], ['training_center', 'training_institute']],
            [['education', 'professional_training_academy'], ['training_center', 'professional_training_center']],
            [['education', 'computer_it_training_institute'], ['training_center', 'it_training_center']],
            [['education', 'vocational_institute'], ['training_center', 'vocational_training_center']],
            [['education', 'dance_academy'], ['training_center', 'dance_academy']],
        ];

        if (!\Illuminate\Support\Facades\Schema::hasTable('institutes') || !\Illuminate\Support\Facades\Schema::hasTable('industry_template_mappings')) {
            return;
        }
        DB::transaction(function () use ($autoMap) {
            foreach ($autoMap as $pair) {
                [$old, $new] = $pair;
                if (\Illuminate\Support\Facades\Schema::hasTable('institutes')) {
                    $ids = DB::table('institutes')->where('industry', $old[0])->where('sub_industry', $old[1])->pluck('id');
                    if ($ids->isNotEmpty()) {
                        DB::table('institutes')->whereIn('id', $ids)->update(['industry' => $new[0], 'sub_industry' => $new[1]]);
                        Log::info('B2 institutes migrated', ['from' => $old, 'to' => $new, 'ids' => $ids->all()]);
                    }
                }
                $rows = DB::table('industry_template_mappings')->where('industry', $old[0])->where('sub_industry', $old[1])->get();
                foreach ($rows as $row) {
                    $exists = DB::table('industry_template_mappings')->where('industry', $new[0])->where('sub_industry', $new[1])->where(function ($q) use ($row) {
                        if ($row->country_id === null) {
                            $q->whereNull('country_id');
                        } else {
                            $q->where('country_id', $row->country_id);
                        }
                    })->exists();
                    if (!$exists) {
                        DB::table('industry_template_mappings')->where('id', $row->id)->update(['industry' => $new[0], 'sub_industry' => $new[1]]);
                    }
                }
            }
            // ensure canonical mappings
            $this->ensureMapping('education', 'polytechnic', 'technical_institute');
            $this->ensureMapping('education', 'school', 'school');
            $this->ensureMapping('education', 'college', 'college');
            $this->ensureMapping('education', 'university', 'university');
            $this->ensureMapping('training_center', 'training_institute', 'training_institute');
            $this->ensureMapping('training_center', 'professional_training_center', 'training_institute');
            $this->ensureMapping('training_center', 'it_training_center', 'training_institute');
            $this->ensureMapping('training_center', 'vocational_training_center', 'vocational_institute');
            $this->ensureMapping('training_center', 'dance_academy', 'dance_academy');
            $this->ensureMapping('training_center', null, 'training_institute');
        });
    }

    public function down(): void
    {
        $autoMap = [
            [['education', 'institution'], ['training_center', 'training_institute']],
            [['education', 'professional_training_academy'], ['training_center', 'professional_training_center']],
            [['education', 'computer_it_training_institute'], ['training_center', 'it_training_center']],
            [['education', 'vocational_institute'], ['training_center', 'vocational_training_center']],
            [['education', 'dance_academy'], ['training_center', 'dance_academy']],
        ];
        if (!\Illuminate\Support\Facades\Schema::hasTable('institutes') || !\Illuminate\Support\Facades\Schema::hasTable('industry_template_mappings')) {
            return;
        }
        DB::transaction(function () use ($autoMap) {
            foreach (array_reverse($autoMap) as $pair) {
                [$old, $new] = $pair;
                $ids = DB::table('institutes')->where('industry', $new[0])->where('sub_industry', $new[1])->pluck('id');
                if ($ids->isNotEmpty()) {
                    DB::table('institutes')->whereIn('id', $ids)->update(['industry' => $old[0], 'sub_industry' => $old[1]]);
                }
                $rows = DB::table('industry_template_mappings')->where('industry', $new[0])->where('sub_industry', $new[1])->get();
                foreach ($rows as $row) {
                    $exists = DB::table('industry_template_mappings')->where('industry', $old[0])->where('sub_industry', $old[1])->where(function ($q) use ($row) {
                        if ($row->country_id === null) {
                            $q->whereNull('country_id');
                        } else {
                            $q->where('country_id', $row->country_id);
                        }
                    })->exists();
                    if (!$exists) {
                        DB::table('industry_template_mappings')->where('id', $row->id)->update(['industry' => $old[0], 'sub_industry' => $old[1]]);
                    }
                }
            }
        });
    }

    private function ensureMapping(string $industry, ?string $sub, string $templateCode): void
    {
        $tpl = DB::table('structure_templates')->where('code', $templateCode)->where('is_global', true)->first();
        if (!$tpl) {
            return;
        }
        $exists = DB::table('industry_template_mappings')->where('industry', $industry)->where(function ($q) use ($sub) {
            if ($sub === null) {
                $q->whereNull('sub_industry');
            } else {
                $q->where('sub_industry', $sub);
            }
        })->whereNull('country_id')->exists();
        if (!$exists) {
            DB::table('industry_template_mappings')->insert([
                'industry' => $industry,
                'sub_industry' => $sub,
                'country_id' => null,
                'structure_template_id' => $tpl->id,
                'priority' => $sub === null ? 999 : 100,
                'status' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};
