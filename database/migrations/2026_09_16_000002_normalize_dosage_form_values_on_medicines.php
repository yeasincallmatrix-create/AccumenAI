<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $map = [
            'tab' => 'Tablet',
            'tabs' => 'Tablet',
            'tablet' => 'Tablet',
            'tablets' => 'Tablet',
            'cap' => 'Capsule',
            'caps' => 'Capsule',
            'capsule' => 'Capsule',
            'capsules' => 'Capsule',
            'syr' => 'Syrup',
            'syrup' => 'Syrup',
            'susp' => 'Suspension',
            'suspension' => 'Suspension',
            'inj' => 'Injection',
            'injection' => 'Injection',
            'drop' => 'Drops',
            'drops' => 'Drops',
            'inh' => 'Inhaler',
            'inhaler' => 'Inhaler',
            'cream' => 'Cream',
            'ointment' => 'Ointment',
            'gel' => 'Gel',
            'spray' => 'Spray',
            'supp' => 'Suppository',
            'suppository' => 'Suppository',
            'sachet' => 'Sachet',
            'powder' => 'Powder',
            'solution' => 'Solution',
            'sol' => 'Solution',
            'lotion' => 'Lotion',
            'patch' => 'Patch',
        ];

        $updated = 0;
        $unmappable = [];

        DB::table('medicines')->orderBy('id')->chunk(500, function ($rows) use ($map, &$updated, &$unmappable) {
            foreach ($rows as $row) {
                $raw = strtolower(trim($row->dosage_form ?? ''));
                if ($raw === '') {
                    continue;
                }

                $canonical = $map[$raw] ?? null;

                // Try prefix match
                if (! $canonical) {
                    foreach ($map as $key => $value) {
                        if (str_starts_with($key, $raw)) {
                            $canonical = $value;
                            break;
                        }
                    }
                }

                if ($canonical && $canonical !== $row->dosage_form) {
                    DB::table('medicines')->where('id', $row->id)->update([
                        'dosage_form' => $canonical,
                    ]);
                    $updated++;
                } elseif (! $canonical) {
                    $unmappable[] = $row->dosage_form;
                }
            }
        });

        // Log results for audit
        \Log::info("Dosage form normalization: {$updated} rows updated, ".count($unmappable)." unmappable values", [
            'unmappable' => array_unique($unmappable),
        ]);
    }

    public function down(): void
    {
        // No reverse — normalization is intentional
    }
};
