<?php

namespace App\Console\Commands;

use App\Models\Institute;
use App\Services\Demo\DemoDataService;
use Illuminate\Console\Command;

class DemoSeedCommand extends Command
{
    protected $signature = 'demo:seed {--industry= : Industry to seed}
                             {--institute= : Existing institute ID or slug}
                             {--name= : Organization name (auto-created if no --institute)}
                             {--country=Bangladesh : Country for the organization}
                             {--password=12345678 : Owner password}
                             {--force : Re-seed even if data exists}';

    protected $description = 'Create a demo organization with ~50 industry-aware entities and login accounts';

    public function handle(DemoDataService $demo): int
    {
        if (app()->environment('production') && ! config('app.demo_seed_enabled', env('DEMO_SEED_ENABLED', false))) {
            $this->error('demo:seed is disabled in production. Set DEMO_SEED_ENABLED=true to enable explicitly, or run in local/testing.');
            return self::FAILURE;
        }
        if (app()->environment('production') && ! $this->option('force')) {
            $this->error('Refusing to run demo:seed in production without --force.');
            return self::FAILURE;
        }

        $industry = $this->option('industry') ?? $this->chooseIndustry();
        $subIndustry = $this->chooseSubIndustry($industry);
        $country = $this->option('country');
        $password = $this->option('password');

        $institute = $this->resolveInstitute($industry, $subIndustry, $country);

        $ownerEmail = $demo->ownerEmail($industry, $subIndustry);
        $owner = \App\Models\User::where('email', $ownerEmail)->first();

        if (! $owner) {
            $this->info('Creating owner account...');
            $owner = $demo->createOwnerAccount($institute, $industry, $subIndustry, $password);
        }

        $this->info('Seeding demo data for: '.$institute->name);

        $result = $demo->seed($institute, $owner, ['force' => $this->option('force')]);

        if (isset($result['skipped'])) {
            $this->warn($result['message']);

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info('Demo organization created successfully.');
        $this->newLine();

        $this->table(['Field', 'Value'], [
            ['Industry', $industry],
            ['Sub-industry', $subIndustry ?? '(none)'],
            ['Organization', $institute->name],
            ['Slug', $institute->slug],
            ['Owner Login', $owner->email],
            ['Password', $password],
        ]);

        $this->newLine();
        $this->info('Demo Records:');
        foreach (['students', 'teachers', 'staff', 'guardians', 'patients', 'customers', 'suppliers', 'employees', 'contacts', 'items'] as $key) {
            if (isset($result[$key]) && $result[$key] > 0) {
                $this->line('  '.ucfirst($key).': '.$result[$key]);
            }
        }

        $this->newLine();
        $this->info('Login URL: '.route('login'));

        return self::SUCCESS;
    }

    private function chooseIndustry(): string
    {
        $industries = config('industry_rules.global.industries', []);

        $selected = $this->choice(
            'Select industry:',
            array_values($industries),
            0
        );

        return array_search($selected, $industries) ?: 'education';
    }

    private function chooseSubIndustry(string $industry): ?string
    {
        $subs = config('industry_rules.Bangladesh.'.$industry, []);

        if (empty($subs)) {
            return null;
        }

        $options = ['(none)', ...array_values($subs)];
        $selected = $this->choice('Select sub-industry:', $options, 0);

        if ($selected === '(none)') {
            return null;
        }

        return array_search($selected, $subs) ?: null;
    }

    private function resolveInstitute(string $industry, ?string $subIndustry, string $country): Institute
    {
        $existingId = $this->option('institute');

        if ($existingId) {
            $institute = is_numeric($existingId)
                ? Institute::find($existingId)
                : Institute::where('slug', $existingId)->first();

            if ($institute) {
                return $institute;
            }

            $this->warn("Institute [{$existingId}] not found. Creating new one.");
        }

        $name = $this->option('name') ?? $this->ask('Organization name',ucwords(str_replace('_', ' ', $subIndustry ?? $industry)).' Demo');

        $slug = \Illuminate\Support\Str::slug($name);
        $suffix = 2;
        while (Institute::where('slug', $slug)->exists()) {
            $slug = $slug.'-'.$suffix;
            $suffix++;
        }

        return Institute::create([
            'name' => $name,
            'slug' => $slug,
            'industry' => $industry,
            'sub_industry' => $subIndustry,
            'country' => $country,
            'status' => 'active',
        ]);
    }
}
