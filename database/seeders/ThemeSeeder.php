<?php

namespace Database\Seeders;

use App\Models\Theme;
use Illuminate\Database\Seeder;

/**
 * Seeds the platform themes tests look up by slug (ocean-blue as the
 * single default, royal-purple as the alternate). Slug unique.
 * Idempotent via firstOrCreate. Seeded for tests (B82).
 */
class ThemeSeeder extends Seeder
{
    public function run(): void
    {
        Theme::firstOrCreate(
            ['slug' => 'ocean-blue'],
            [
                'name' => 'Ocean Blue',
                'primary_color' => '#0D6EFD',
                'secondary_color' => '#FFC107',
                'is_dark' => false,
                'is_default' => true,
                'status' => 'active',
            ]
        );

        Theme::firstOrCreate(
            ['slug' => 'royal-purple'],
            [
                'name' => 'Royal Purple',
                'primary_color' => '#6F42C1',
                'secondary_color' => '#FFC107',
                'is_dark' => false,
                'is_default' => false,
                'status' => 'active',
            ]
        );
    }
}
